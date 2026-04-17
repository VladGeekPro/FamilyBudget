from __future__ import annotations

import argparse
import calendar
import difflib
import json
import os
import re
import sys
from dataclasses import dataclass
from datetime import date, datetime, timedelta
from typing import Any, Optional

import unicodedata

import iuliia
import torch
from dateparser.search import search_dates
from pymorphy3 import MorphAnalyzer
from rapidfuzz import fuzz
from rapidfuzz.distance import Levenshtein
from sentence_transformers import SentenceTransformer
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from gliner import GLiNER


MORPH = MorphAnalyzer()
DEVICE = "cuda" if torch.cuda.is_available() else "cpu"
_MODEL: Optional[SentenceTransformer] = None
_AMOUNT_MODEL: Optional[Any] = None
_WHISPER_MODEL: Optional[Any] = None


def get_embedding_model() -> SentenceTransformer:
    global _MODEL

    if _MODEL is None:
        _MODEL = SentenceTransformer("Qwen/Qwen3-Embedding-0.6B", device=DEVICE)

    return _MODEL


def get_amount_model() -> Optional[Any]:
    global _AMOUNT_MODEL

    if _AMOUNT_MODEL is None and GLiNER is not None:
        _AMOUNT_MODEL = GLiNER.from_pretrained("urchade/gliner_multi-v2.1")

    return _AMOUNT_MODEL


def get_whisper_model() -> Any:
    global _WHISPER_MODEL

    if _WHISPER_MODEL is None:
        from faster_whisper import WhisperModel

        model_name = os.getenv("EXPENSE_VOICE_MODEL", "small")
        compute_type = "float16" if torch.cuda.is_available() else "int8"
        _WHISPER_MODEL = WhisperModel(model_name, device=DEVICE, compute_type=compute_type)

    return _WHISPER_MODEL

def normalize_text(text: str) -> str:
    text = unicodedata.normalize("NFKD", text.lower())
    text = "".join(ch for ch in text if not unicodedata.combining(ch))
    return re.sub(r"[^\w\s]", "", text).strip()


def tokenize_text(text: str) -> list[str]:
    return normalize_text(text).split()


def lemmatize_word(word: str) -> str:
    return MORPH.parse(word)[0].normal_form if re.fullmatch(r"[а-я]+", word) else word


def lemmatize_text(text: str) -> list[str]:
    return [lemmatize_word(word) for word in tokenize_text(text)]


def variants(text: str) -> list[str]:
    base = normalize_text(text)
    result = [base]

    for schema in (iuliia.WIKIPEDIA, iuliia.MOSMETRO, iuliia.ALA_LC):
        try:
            v = normalize_text(schema.translate(base))
            if v and v not in result:
                result.append(v)
        except Exception:
            pass

    for v in list(result):
        core = " ".join(w for w in v.split() if len(w) > 1 and any(ch.isalpha() for ch in w))
        core = normalize_text(core)
        if core and core not in result:
            result.insert(0, core)

    return result


def token_alignment_score(phrase_variant: str, candidate_tokens: list[str]) -> float:
    phrase_tokens = [t for t in phrase_variant.split() if len(t) > 2]
    if not phrase_tokens or not candidate_tokens:
        return 0.0
    best_scores = []
    for pt in phrase_tokens:
        best = 0.0
        for ct in candidate_tokens:
            sim = Levenshtein.normalized_similarity(pt, ct)
            if sim > best:
                best = sim
        best_scores.append(best)
    return sum(best_scores) / len(best_scores)


def length_penalty(phrase_len: int, candidate_len: int) -> float:
    """Штраф за несоответствие длины фразы и поставщика.

    Если фраза намного короче/длиннее поставщика, это вероятнее false positive.
    Возвращает коэффициент штрафа от 0.5 до 1.0.
    """
    if phrase_len == 0 or candidate_len == 0:
        return 0.0
    ratio = min(phrase_len, candidate_len) / max(phrase_len, candidate_len)
    # ratio близко к 1.0 = одинаковая длина = хорошо
    # ratio близко к 0.0 = разные длины = плохо
    if ratio >= 0.80:
        return 1.0
    elif ratio >= 0.60:
        return 0.90
    elif ratio >= 0.40:
        return 0.70
    else:
        return 0.50


def canonicalize_for_similarity(text: str) -> str:
    t = normalize_text(text).replace(" ", "")
    replacements = (
        ("sch", "sh"),
        ("tch", "ch"),
        ("dzh", "j"),
        ("zh", "j"),
        ("sh", "s"),
        ("ch", "c"),
        ("kh", "h"),
        ("ph", "f"),
        ("ck", "k"),
        ("qu", "k"),
        ("q", "k"),
        ("w", "v"),
        ("x", "ks"),
        ("ts", "z"),
        ("tz", "z"),
    )
    for src, dst in replacements:
        t = t.replace(src, dst)
    return re.sub(r"(.)\1+", r"\1", t)


def phonetic_similarity(left: str, right: str) -> float:
    l = canonicalize_for_similarity(left)
    r = canonicalize_for_similarity(right)
    if not l or not r:
        return 0.0
    char = fuzz.ratio(l, r) / 100.0
    lev = Levenshtein.normalized_similarity(l, r)
    return 0.50 * char + 0.50 * lev


@dataclass(frozen=True)
class ParsedDate:
    date_iso: str
    matched_expression: Optional[str]


@dataclass(frozen=True)
class Token:
    original: str
    normalized: str
    raw_lemma: str
    lemma: str
    lemma_correction: Optional[str]
    start: int
    end: int
    lemma_start: int
    lemma_end: int


WORD_RE = re.compile(r"[0-9]+(?:[./-][0-9]+)*|[а-яё]+", re.IGNORECASE)


class UniversalDateParser:
    MONTHS = {
        "январь": 1, "февраль": 2, "март": 3, "апрель": 4, "май": 5, "июнь": 6,
        "июль": 7, "август": 8, "сентябрь": 9, "октябрь": 10, "ноябрь": 11, "декабрь": 12,
    }
    WEEKDAYS = {
        "понедельник": 0, "вторник": 1, "среда": 2, "четверг": 3,
        "пятница": 4, "суббота": 5, "воскресенье": 6,
    }
    DIRECT_RELATIVE = {"послезавтра": 2, "позавчера": -2, "сегодня": 0, "вчера": -1, "завтра": 1}
    ORDINAL_DAYS = {
        "первый": 1, "второй": 2, "третий": 3, "четвертый": 4, "пятый": 5, "шестой": 6,
        "седьмой": 7, "восьмой": 8, "девятый": 9, "десятый": 10, "одиннадцатый": 11,
        "двенадцатый": 12, "тринадцатый": 13, "четырнадцатый": 14, "пятнадцатый": 15,
        "шестнадцатый": 16, "семнадцатый": 17, "восемнадцатый": 18, "девятнадцатый": 19,
        "двадцатый": 20, "двадцать первый": 21, "двадцать второй": 22, "двадцать третий": 23,
        "двадцать четвертый": 24, "двадцать пятый": 25, "двадцать шестой": 26,
        "двадцать седьмой": 27, "двадцать восьмой": 28, "двадцать девятый": 29,
        "тридцатый": 30, "тридцать первый": 31,
    }
    NUMBER_WORDS = {
        "ноль": 0, "один": 1, "два": 2, "три": 3, "четыре": 4, "пять": 5, "шесть": 6,
        "семь": 7, "восемь": 8, "девять": 9, "десять": 10, "одиннадцать": 11,
        "двенадцать": 12, "тринадцать": 13, "четырнадцать": 14, "пятнадцать": 15,
        "шестнадцать": 16, "семнадцать": 17, "восемнадцать": 18, "девятнадцать": 19,
        "двадцать": 20, "тридцать": 30,
    }
    FUTURE_HINTS = ("завтра", "послезавтра", "через", "быть", "заплатить", "следующий", "последующий")
    PAST_HINTS = ("вчера", "позавчера", "назад", "прошлый", "предыдущий", "оплатить", "купить", "заказать")

    DIRECT_RELATIVE_RE = re.compile(r"(?<!\S)(послезавтра|позавчера|сегодня|вчера|завтра)(?!\S)")
    WEEK_RELATIVE_RE = re.compile(
        r"(?<!\S)на (?P<which>следующий|последующий|прошлый|предыдущий|этот) неделя"
        r"(?: (?P<prep>в|во|на) (?P<weekday>понедельник|вторник|среда|четверг|пятница|суббота|воскресенье))?(?!\S)"
    )
    QUANTITY_RELATIVE_RE = re.compile(
        r"(?<!\S)(?P<number>\d+|[а-яё]+(?: [а-яё]+)?) "
        r"(?P<unit>месяц|неделя|день) "
        r"(?P<ago>назад)"
        r"(?: (?P<prep>в|во|на) (?P<weekday>понедельник|вторник|среда|четверг|пятница|суббота|воскресенье))?(?!\S)",
        re.IGNORECASE,
    )
    FORWARD_QUANTITY_RE = re.compile(
        r"(?<!\S)(?P<through>через) "
        r"(?P<number>\d+|[а-яё]+(?: [а-яё]+)?) "
        r"(?P<unit>месяц|неделя|день)"
        r"(?: (?P<prep>в|во|на) (?P<weekday>понедельник|вторник|среда|четверг|пятница|суббота|воскресенье))?(?!\S)",
        re.IGNORECASE,
    )
    FORWARD_SINGLE_UNIT_RE = re.compile(
        r"(?<!\S)(?P<through>через) "
        r"(?P<unit>месяц|неделя|день)"
        r"(?: (?P<prep>в|во|на) (?P<weekday>понедельник|вторник|среда|четверг|пятница|суббота|воскресенье))?(?!\S)",
        re.IGNORECASE,
    )
    TEXTUAL_ABSOLUTE_RE = re.compile(
        r"(?<!\S)(?P<day>\d{1,2}|[а-яё]+(?: [а-яё]+)?) "
        r"(?P<month>январь|февраль|март|апрель|май|июнь|июль|август|сентябрь|октябрь|ноябрь|декабрь)"
        r"(?: (?P<year>\d{4}))?(?!\S)",
        re.IGNORECASE,
    )
    PERIOD_EDGE_RE = re.compile(
        r"(?<!\S)(?:в )?(?P<edge>начало|конец) (?P<which>этот|следующий|последующий|прошлый|предыдущий) (?P<unit>неделя|месяц)(?!\S)",
        re.IGNORECASE,
    )

    @classmethod
    def temporal_vocabulary(cls) -> set[str]:
        vocab: set[str] = set()
        vocab.update(cls.MONTHS)
        vocab.update(cls.WEEKDAYS)
        vocab.update(cls.DIRECT_RELATIVE)
        vocab.update(cls.ORDINAL_DAYS)
        vocab.update(cls.NUMBER_WORDS)
        vocab.update({
            "неделя", "месяц", "день", "назад", "через", "начало", "конец", "на", "в", "во",
            "этот", "прошлый", "предыдущий", "следующий", "последующий",
        })
        return vocab

    @staticmethod
    def similarity(left: str, right: str) -> float:
        return difflib.SequenceMatcher(None, left, right).ratio()

    @classmethod
    def pick_temporal_correction(cls, normalized: str, raw_lemma: str) -> tuple[str, Optional[str]]:
        vocab = cls.temporal_vocabulary()
        if raw_lemma in vocab or not normalized.isalpha() or len(normalized) < 5:
            return raw_lemma, None

        candidates = list(difflib.get_close_matches(normalized, list(vocab), n=4, cutoff=0.74))
        candidates.extend(difflib.get_close_matches(raw_lemma, list(vocab), n=4, cutoff=0.74))
        candidates = list(dict.fromkeys(candidates))
        if not candidates:
            return raw_lemma, None

        best = max(candidates, key=lambda item: max(cls.similarity(normalized, item), cls.similarity(raw_lemma, item)))
        best_score = max(cls.similarity(normalized, best), cls.similarity(raw_lemma, best))
        return (best, f"{raw_lemma}->{best}") if best_score >= 0.80 else (raw_lemma, None)

    @staticmethod
    def normalize_word(word: str) -> str:
        return word.lower().replace("ё", "е")

    @classmethod
    def lemmatize(cls, word: str) -> str:
        return MORPH.parse(word)[0].normal_form if word.isalpha() else word

    @classmethod
    def tokenize(cls, text: str) -> list[Token]:
        tokens: list[Token] = []
        lemma_cursor = 0

        for match in WORD_RE.finditer(text):
            original = match.group(0)
            normalized = cls.normalize_word(original)
            raw_lemma = cls.lemmatize(normalized)
            lemma, correction = cls.pick_temporal_correction(normalized, raw_lemma)
            lemma_start = lemma_cursor
            lemma_end = lemma_start + len(lemma)
            tokens.append(Token(original, normalized, raw_lemma, lemma, correction, match.start(), match.end(), lemma_start, lemma_end))
            lemma_cursor = lemma_end + 1

        return tokens

    @staticmethod
    def lemma_text(tokens: list[Token]) -> str:
        return " ".join(token.lemma for token in tokens)

    @staticmethod
    def surface_text(text: str, tokens: list[Token], start_idx: int, end_idx: int) -> str:
        return text[tokens[start_idx].start:tokens[end_idx].end].strip() if tokens else ""

    @staticmethod
    def lemma_span_to_token_range(tokens: list[Token], span: tuple[int, int]) -> Optional[tuple[int, int]]:
        start_char, end_char = span
        start_idx = end_idx = None

        for idx, token in enumerate(tokens):
            if start_idx is None and token.lemma_start <= start_char < token.lemma_end:
                start_idx = idx
            if token.lemma_start < end_char <= token.lemma_end:
                end_idx = idx
                break

        return (start_idx, end_idx) if start_idx is not None and end_idx is not None else None

    @classmethod
    def make_parsed_date(cls, text: str, tokens: list[Token], match, parsed_date: date) -> Optional[ParsedDate]:
        token_span = cls.lemma_span_to_token_range(tokens, match.span())
        if token_span is None:
            return None
        return ParsedDate(parsed_date.isoformat(), cls.surface_text(text, tokens, token_span[0], token_span[1]))

    @classmethod
    def parse_number_phrase(cls, phrase: str) -> Optional[int]:
        phrase = phrase.strip()
        if not phrase:
            return None
        if phrase.isdigit():
            return int(phrase)

        parts = phrase.split()
        if len(parts) == 1:
            return cls.NUMBER_WORDS.get(parts[0])
        if len(parts) == 2 and parts[0] in {"двадцать", "тридцать"}:
            base = cls.NUMBER_WORDS.get(parts[0])
            addon = cls.NUMBER_WORDS.get(parts[1])
            if base is not None and addon is not None and 1 <= addon <= 9:
                return base + addon
        return None

    @classmethod
    def parse_day_phrase(cls, phrase: str) -> Optional[int]:
        if phrase.isdigit():
            value = int(phrase)
            return value if 1 <= value <= 31 else None
        return cls.ORDINAL_DAYS.get(phrase.strip())

    @staticmethod
    def shift_months(value: date, months: int) -> date:
        month_index = value.month - 1 + months
        year = value.year + month_index // 12
        month = month_index % 12 + 1
        day = min(value.day, calendar.monthrange(year, month)[1])
        return date(year, month, day)

    @staticmethod
    def parse_numeric_absolute(tokens: list[Token]) -> Optional[ParsedDate]:
        for token in tokens:
            separator = "." if "." in token.original else "-" if "-" in token.original else "/" if "/" in token.original else None
            if separator is None:
                continue

            parts = token.original.split(separator)
            if len(parts) != 3 or not all(part.isdigit() for part in parts):
                continue

            try:
                if len(parts[0]) == 4:
                    parsed = date(int(parts[0]), int(parts[1]), int(parts[2]))
                elif len(parts[2]) == 4:
                    parsed = date(int(parts[2]), int(parts[1]), int(parts[0]))
                else:
                    continue
                return ParsedDate(parsed.isoformat(), token.original)
            except ValueError:
                continue

        return None

    @classmethod
    def parse_textual_absolute(cls, text: str, tokens: list[Token], reference_date: date) -> Optional[ParsedDate]:
        lemma_text = cls.lemma_text(tokens)
        for match in cls.TEXTUAL_ABSOLUTE_RE.finditer(lemma_text):
            day = cls.parse_day_phrase(match.group("day"))
            month = cls.MONTHS.get(match.group("month"))
            if day is None or month is None:
                continue

            year = int(match.group("year")) if match.group("year") else reference_date.year
            try:
                parsed = date(year, month, day)
            except ValueError:
                continue

            result = cls.make_parsed_date(text, tokens, match, parsed)
            if result is not None:
                return result

        return None

    @classmethod
    def parse_direct_relative(cls, text: str, tokens: list[Token], reference_date: date) -> Optional[ParsedDate]:
        lemma_text = cls.lemma_text(tokens)
        match = cls.DIRECT_RELATIVE_RE.search(lemma_text)
        if not match:
            return None

        parsed = reference_date + timedelta(days=cls.DIRECT_RELATIVE[match.group(1)])
        return cls.make_parsed_date(text, tokens, match, parsed)

    @staticmethod
    def week_monday(value: date) -> date:
        return value - timedelta(days=value.weekday())

    @classmethod
    def parse_week_relative(cls, text: str, tokens: list[Token], reference_date: date) -> Optional[ParsedDate]:
        lemma_text = cls.lemma_text(tokens)
        match = cls.WEEK_RELATIVE_RE.search(lemma_text)
        if not match:
            return None

        offsets = {"следующий": 7, "последующий": 7, "прошлый": -7, "предыдущий": -7, "этот": 0}
        anchor = reference_date + timedelta(days=offsets[match.group("which")])

        if match.group("weekday"):
            anchor = cls.week_monday(anchor) + timedelta(days=cls.WEEKDAYS[match.group("weekday")])

        return cls.make_parsed_date(text, tokens, match, anchor)

    @classmethod
    def parse_period_edge(cls, text: str, tokens: list[Token], reference_date: date) -> Optional[ParsedDate]:
        lemma_text = cls.lemma_text(tokens)
        match = cls.PERIOD_EDGE_RE.search(lemma_text)
        if not match:
            return None

        edge, which, unit = match.group("edge"), match.group("which"), match.group("unit")

        if unit == "неделя":
            offsets = {"прошлый": -7, "предыдущий": -7, "этот": 0, "следующий": 7, "последующий": 7}
            monday = cls.week_monday(reference_date + timedelta(days=offsets[which]))
            parsed_date = monday if edge == "начало" else monday + timedelta(days=6)
        else:
            month_offset = {"прошлый": -1, "предыдущий": -1, "этот": 0, "следующий": 1, "последующий": 1}[which]
            shifted = cls.shift_months(date(reference_date.year, reference_date.month, 1), month_offset)
            parsed_date = shifted if edge == "начало" else date(shifted.year, shifted.month, calendar.monthrange(shifted.year, shifted.month)[1])

        return cls.make_parsed_date(text, tokens, match, parsed_date)

    @classmethod
    def parse_quantity_relative(cls, text: str, tokens: list[Token], reference_date: date) -> Optional[ParsedDate]:
        lemma_text = cls.lemma_text(tokens)

        for regex, direction in ((cls.QUANTITY_RELATIVE_RE, -1), (cls.FORWARD_QUANTITY_RE, 1)):
            for match in regex.finditer(lemma_text):
                number = cls.parse_number_phrase(match.group("number"))
                if number is None:
                    continue

                unit = match.group("unit")
                if unit == "месяц":
                    anchor = cls.shift_months(reference_date, direction * number)
                else:
                    days = number * 7 if unit == "неделя" else number
                    anchor = reference_date + timedelta(days=direction * days)

                if match.group("weekday"):
                    anchor = cls.week_monday(anchor) + timedelta(days=cls.WEEKDAYS[match.group("weekday")])

                result = cls.make_parsed_date(text, tokens, match, anchor)
                if result is not None:
                    return result

        for match in cls.FORWARD_SINGLE_UNIT_RE.finditer(lemma_text):
            unit = match.group("unit")
            if unit == "месяц":
                anchor = cls.shift_months(reference_date, 1)
            else:
                days = 7 if unit == "неделя" else 1
                anchor = reference_date + timedelta(days=days)

            if match.group("weekday"):
                anchor = cls.week_monday(anchor) + timedelta(days=cls.WEEKDAYS[match.group("weekday")])

            result = cls.make_parsed_date(text, tokens, match, anchor)
            if result is not None:
                return result

        return None

    @classmethod
    def preference_for_text(cls, tokens: list[Token]) -> str:
        lemmas = [token.lemma for token in tokens]
        future = sum(1 for hint in cls.FUTURE_HINTS if hint in lemmas)
        past = sum(1 for hint in cls.PAST_HINTS if hint in lemmas)
        return "future" if future > past else "past"

    @staticmethod
    def choose_best(matches: list[tuple[str, datetime]]) -> tuple[str, datetime]:
        return sorted(matches, key=lambda item: (len(item[0]), -item[1].timestamp()), reverse=True)[0]

    def parse(self, text: str, reference_date: date) -> Optional[ParsedDate]:
        tokens = self.tokenize(text)

        for parser in (
            lambda: self.parse_numeric_absolute(tokens),
            lambda: self.parse_textual_absolute(text, tokens, reference_date),
            lambda: self.parse_direct_relative(text, tokens, reference_date),
            lambda: self.parse_week_relative(text, tokens, reference_date),
            lambda: self.parse_period_edge(text, tokens, reference_date),
            lambda: self.parse_quantity_relative(text, tokens, reference_date),
        ):
            parsed = parser()
            if parsed is not None:
                return parsed

        normalized = " ".join(token.normalized for token in tokens)
        relative_base = datetime.combine(reference_date, datetime.min.time()).replace(hour=12)
        result = search_dates(
            normalized,
            languages=["ru"],
            settings={
                "RELATIVE_BASE": relative_base,
                "PREFER_DATES_FROM": self.preference_for_text(tokens),
                "STRICT_PARSING": False,
                "REQUIRE_PARTS": [],
                "NORMALIZE": True,
                "RETURN_AS_TIMEZONE_AWARE": False,
                "DATE_ORDER": "DMY",
            },
        )

        filtered: list[tuple[str, datetime]] = []
        for matched, value in result or []:
            if isinstance(value, datetime) and not matched.strip().isdigit() and 2020 <= value.year <= 2100:
                filtered.append((matched.strip(), value))

        if not filtered:
            return None

        matched_expression, value = self.choose_best(filtered)
        return ParsedDate(date_iso=value.date().isoformat(), matched_expression=matched_expression)


class ExpenseDateExtractor:
    def __init__(self) -> None:
        self.parser = UniversalDateParser()

    def extract(self, text: str, reference_date: str | date | None = None) -> dict[str, Any]:
        ref_date = self.to_date(reference_date)
        parsed = self.parser.parse(text=text, reference_date=ref_date)

        return {
            "date": datetime.strptime(parsed.date_iso, "%Y-%m-%d").strftime("%d.%m.%Y") if parsed else None,
            "date_iso": parsed.date_iso if parsed else None,
            "matched_date_phrase": parsed.matched_expression if parsed else None,
        }

    @staticmethod
    def to_date(value: str | date) -> date:
        return value if isinstance(value, date) else datetime.strptime(value, "%Y-%m-%d").date()


class ExpensePhraseMatcher:
    def __init__(self, values: list[str]) -> None:
        self.values = values
        self.norm_values = [normalize_text(value) for value in values]
        self.value_tokens = [value.split() for value in self.norm_values]
        self.number_tokens = {token for tokens in self.value_tokens for token in tokens if token.isdigit()}
        self.max_words = max((len(tokens) for tokens in self.value_tokens), default=1)
        self.tfidf = TfidfVectorizer(analyzer="char_wb", ngram_range=(3, 5))
        self.value_matrix = self.tfidf.fit_transform(self.norm_values)

    def score_phrase(self, phrase: str) -> dict[str, Any]:
        phrase_variants = variants(phrase)
        query_matrix = self.tfidf.transform(phrase_variants)
        tfidf_scores = cosine_similarity(query_matrix, self.value_matrix)

        best: dict[str, Any] = {"value": None, "score": -1.0, "phrase": phrase, "phon": 0.0}

        for index, candidate in enumerate(self.norm_values):
            local_score = -1.0
            local_phon = 0.0
            for variant_index, variant in enumerate(phrase_variants):
                char_score = fuzz.ratio(variant, candidate) / 100.0
                tfidf_score = float(tfidf_scores[variant_index, index])
                penalty = length_penalty(len(variant), len(candidate))
                phon = phonetic_similarity(variant, candidate)

                if len(variant.split()) == 1 and len(candidate.split()) == 1:
                    lev_score = Levenshtein.normalized_similarity(variant, candidate)
                    score = (0.50 * lev_score + 0.20 * char_score + 0.10 * tfidf_score + 0.20 * phon) * penalty
                else:
                    align_score = token_alignment_score(variant, self.value_tokens[index])
                    token_score = fuzz.token_set_ratio(variant, candidate) / 100.0
                    score = (0.30 * char_score + 0.20 * token_score + 0.10 * tfidf_score + 0.20 * align_score + 0.20 * phon) * penalty

                if score > local_score:
                    local_score = score
                    local_phon = phon

            if local_score > best["score"]:
                best = {
                    "value": self.values[index],
                    "score": local_score,
                    "phrase": phrase,
                    "phon": local_phon,
                }
        return best

    def build_phrases(self, text: str, excluded_tokens: Optional[set[str]] = None) -> list[str]:
        excluded = excluded_tokens or set()
        tokens = [
            token for token in tokenize_text(text)
            if (((len(token) > 1 and not token.isdigit()) or token in self.number_tokens) and token not in excluded)
        ]

        phrases: list[str] = []
        seen: set[str] = set()
        for i in range(len(tokens)):
            for j in range(i + 1, min(i + 1 + self.max_words, len(tokens) + 1)):
                phrase = " ".join(tokens[i:j])
                if phrase not in seen:
                    seen.add(phrase)
                    phrases.append(phrase)
        return phrases

    def extract(
        self,
        text: str,
        excluded_tokens: Optional[set[str]] = None,
        noise_terms: Optional[set[str]] = None,
        min_score: float = 0.56,
        min_phonetic_score: float = 0.62,
    ) -> dict[str, Any]:
        phrases = self.build_phrases(text, excluded_tokens=excluded_tokens)
        results = [self.score_phrase(phrase) for phrase in phrases]

        best_by_value: dict[str, dict[str, Any]] = {}
        for row in results:
            value = row["value"]
            if value not in best_by_value or row["score"] > best_by_value[value]["score"]:
                best_by_value[value] = row

        noise = noise_terms or set()

        def is_noise_phrase(phrase: str) -> bool:
            parts = normalize_text(phrase).split()
            return not parts or (len(parts) == 1 and parts[0] in noise)

        ranking = sorted(best_by_value.values(), key=lambda row: row["score"], reverse=True)
        filtered = []
        for row in ranking:
            score = float(row.get("score", -1.0))
            phon = float(row.get("phon", 0.0))
            phrase = str(row.get("phrase") or "")
            if not is_noise_phrase(phrase) and (score > min_score or (score > 0.50 and phon >= min_phonetic_score)):
                filtered.append(row)

        best = filtered[0] if filtered else {"value": None, "score": -1.0, "phrase": None}
        return {
            "value": best["value"],
            "score": round(best["score"], 4) if best["score"] >= 0 else None,
            "phrase": best.get("phrase"),
        }


class ExpenseUserExtractor:
    def __init__(
        self,
        users: list[str],
        suppliers: list[str],
        model: SentenceTransformer,
        threshold: float = 0.6,
    ) -> None:
        self.users = users
        self.model = model
        self.threshold = threshold
        self.supplier_terms = {normalize_text(supplier) for supplier in suppliers}
        self.user_terms = [normalize_text(user) for user in users]
        self.user_embeddings = model.encode(
            [f"passage: {user}" for user in self.user_terms],
            convert_to_tensor=True,
            normalize_embeddings=True,
        )

    def extract(self, text: str, supplier_phrase: str | None = None, date_phrase: str | None = None) -> dict[str, Any]:
        excluded_tokens: set[str] = set()
        if supplier_phrase:
            excluded_tokens.update(normalize_text(supplier_phrase).split())
        if date_phrase:
            excluded_tokens.update(normalize_text(date_phrase).split())

        best_user = None
        best_score = -1.0
        best_phrase = None

        for word in lemmatize_text(text):
            if len(word) < 3:
                continue
            if word in excluded_tokens or word in self.supplier_terms:
                continue

            query_emb = self.model.encode(
                f"query: {word}",
                convert_to_tensor=True,
                normalize_embeddings=True,
            )
            similarities = torch.cosine_similarity(query_emb.unsqueeze(0), self.user_embeddings, dim=1)
            idx = int(torch.argmax(similarities))
            score = similarities[idx].item()

            if score > best_score:
                best_score = score
                best_user = self.users[idx]
                best_phrase = word

        if best_score >= self.threshold:
            return {
                "user": best_user,
                "user_score": round(best_score, 4),
                "matched_user_phrase": best_phrase,
            }

        if re.search(r"(?<!\S)я(?!\S)", normalize_text(text), re.IGNORECASE):
            return {
                "user": "Я",
                "user_score": 1.0,
                "matched_user_phrase": "я",
            }

        return {
            "user": None,
            "user_score": None,
            "matched_user_phrase": None,
        }


class ExpenseSupplierExtractor:
    def __init__(self, suppliers: list[str]) -> None:
        self.suppliers = suppliers
        self.sup_norm = [normalize_text(s) for s in suppliers]
        self.sup_tokens = [s.split() for s in self.sup_norm]
        self.sup_num_sets = [self.numeric_tokens(s) for s in self.sup_norm]
        self.sup_number_tokens = {token for supplier in self.sup_tokens for token in supplier if token.isdigit()}
        self.supplier_lexicon = [
            token
            for token in sorted({tok for tokens in self.sup_tokens for tok in tokens})
            if token and not token.isdigit()
        ]
        self.tfidf = TfidfVectorizer(analyzer="char_wb", ngram_range=(3, 5))
        self.sup_mat = self.tfidf.fit_transform(self.sup_norm)
        self.max_words = max(len(s.split()) for s in self.sup_norm)
        self.variant_cache: dict[str, list[str]] = {}
        self.lexical_token_cache: dict[str, float] = {}
        self.phrase_support_cache: dict[str, float] = {}
        self.noise_terms = {
            "за", "на", "из", "для", "под", "над", "при", "без", "и", "или",
            "купил", "купила", "купили", "покупка", "заказал", "заказала", "заказали",
            "оплатил", "оплатила", "оплатили", "заплатил", "заплатила", "заплатили",
            "был", "была", "было", "были", "утром", "днем", "днём", "вечером", "ночью",
            "товар", "товары", "продукт", "продукты", "десерт", "еда",
            "лей", "лея", "леи", "целых", "сотых", "сом", "сомов", "руб", "рублей", "грн", "usd", "eur",
        }
        self.noise_terms.update(UniversalDateParser.temporal_vocabulary())

    @staticmethod
    def numeric_tokens(text: str) -> set[str]:
        return set(re.findall(r"\d+", text))

    def cached_variants(self, text: str) -> list[str]:
        key = normalize_text(text)
        cached = self.variant_cache.get(key)
        if cached is None:
            cached = variants(key)
            self.variant_cache[key] = cached
        return cached

    @staticmethod
    def split_words(text: str) -> list[str]:
        return [w for w in normalize_text(text).split() if w]

    @classmethod
    def is_supplier_extension(cls, base_supplier: str, extended_supplier: str) -> bool:
        base_tokens = cls.split_words(base_supplier)
        extended_tokens = cls.split_words(extended_supplier)
        return (
            len(base_tokens) < len(extended_tokens)
            and extended_tokens[:len(base_tokens)] == base_tokens
        )

    @classmethod
    def phrase_token_count(cls, phrase: str | None) -> int:
        return len(cls.split_words(phrase or ""))

    @classmethod
    def resolve_overlapping_suppliers(cls, ranking: list[dict[str, Any]]) -> dict[str, Any]:
        if not ranking:
            return {"supplier": None, "score": -1.0, "phrase": None}

        best = ranking[0]
        best_combined = float(best.get("combined", best.get("score", -1.0)))
        best_phrase_len = cls.phrase_token_count(best.get("phrase"))

        for alt in ranking[1:]:
            if not cls.is_supplier_extension(str(best.get("supplier") or ""), str(alt.get("supplier") or "")):
                continue

            alt_combined = float(alt.get("combined", alt.get("score", -1.0)))
            alt_phrase_len = cls.phrase_token_count(alt.get("phrase"))

            # Если есть продолжение и оно по качеству близко, приоритет у более длинного поставщика.
            if alt_phrase_len > best_phrase_len and alt_combined >= best_combined - 0.15:
                best = alt
                best_combined = alt_combined
                best_phrase_len = alt_phrase_len

        return best

    @staticmethod
    def numeric_compatibility_multiplier(phrase_nums: set[str], candidate_nums: set[str]) -> float:

        if not phrase_nums and not candidate_nums:
            return 1.0

        if phrase_nums == candidate_nums:
            return 1.08

        if phrase_nums and candidate_nums:
            return 1.03 if phrase_nums & candidate_nums else 0.80

        return 0.82

    def lexical_support(self, phrase: str) -> float:
        tokens = [token for token in normalize_text(phrase).split() if token and not token.isdigit()]
        if not tokens or not self.supplier_lexicon:
            return 0.0

        support_scores: list[float] = []
        for token in tokens:
            cached = self.lexical_token_cache.get(token)
            if cached is not None:
                support_scores.append(cached)
                continue

            best = 0.0
            for token_variant in self.cached_variants(token):
                for lex in self.supplier_lexicon:
                    lev = Levenshtein.normalized_similarity(token_variant, lex)
                    phon = phonetic_similarity(token_variant, lex)
                    sim = max(lev, phon)
                    if sim > best:
                        best = sim

            self.lexical_token_cache[token] = best
            support_scores.append(best)

        return sum(support_scores) / len(support_scores)

    def score_phrase(self, phrase: str) -> dict[str, Any]:
        vs = self.cached_variants(phrase)
        q = self.tfidf.transform(vs)
        tf = cosine_similarity(q, self.sup_mat)

        best: dict[str, Any] = {"supplier": None, "score": -1.0, "phrase": phrase, "variant": ""}
        for i, cand in enumerate(self.sup_norm):
            local = -1.0
            local_variant = ""
            candidate_nums = self.sup_num_sets[i]
            for j, v in enumerate(vs):
                char = fuzz.ratio(v, cand) / 100.0
                tf_val = float(tf[j, i])
                penalty = length_penalty(len(v), len(cand))
                phon = phonetic_similarity(v, cand)
                phrase_nums = self.numeric_tokens(v)

                if len(v.split()) == 1 and len(cand.split()) == 1:
                    lev = Levenshtein.normalized_similarity(v, cand)
                    val = (0.45 * lev + 0.25 * char + 0.10 * tf_val + 0.20 * phon) * penalty
                else:
                    align = token_alignment_score(v, self.sup_tokens[i])
                    tok = fuzz.token_set_ratio(v, cand) / 100.0
                    val = (0.30 * char + 0.20 * tok + 0.10 * tf_val + 0.20 * align + 0.20 * phon) * penalty

                    compact_v = v.replace(" ", "")
                    compact_cand = cand.replace(" ", "")
                    compact_char = fuzz.ratio(compact_v, compact_cand) / 100.0
                    compact_lev = Levenshtein.normalized_similarity(compact_v, compact_cand)
                    compact_phon = phonetic_similarity(compact_v, compact_cand)
                    compact = max(compact_char, compact_lev, compact_phon)
                    if compact > 0.55:
                        val = max(val, compact * penalty)

                val *= self.numeric_compatibility_multiplier(phrase_nums, candidate_nums)

                if val > local:
                    local = val
                    local_variant = v

            if local > best["score"]:
                best = {"supplier": self.suppliers[i], "score": local, "phrase": phrase, "variant": local_variant}
        return best

    def extract(self, text: str, date_phrase: str | None = None, debug: bool = False) -> dict[str, Any]:
        threshold = 0.50
        excluded_tokens: set[str] = set()
        if date_phrase:
            excluded_tokens.update(normalize_text(date_phrase).split())
        excluded_tokens.update(self.noise_terms)

        raw_tokens = normalize_text(text).split()
        tokens: list[str] = []
        for token in raw_tokens:
            if token in excluded_tokens:
                continue

            if token.isdigit():
                if token in self.sup_number_tokens:
                    tokens.append(token)

                if tokens and len(token) <= 3 and len(tokens[-1]) >= 4 and tokens[-1].isalpha():
                    tokens.append(f"{tokens[-1]}{token}")
                continue

            if len(token) > 1:
                tokens.append(token)

        tokens = [
            t for t in tokens
            if len(t) > 1 and t not in excluded_tokens
        ]

        phrases: list[str] = []
        seen: set[str] = set()
        for i in range(len(tokens)):
            for j in range(i + 1, min(i + 1 + self.max_words, len(tokens) + 1)):
                p = " ".join(tokens[i:j])
                if p not in seen:
                    seen.add(p)
                    phrases.append(p)

        results = [self.score_phrase(p) for p in phrases]
        candidate_rows: list[dict[str, Any]] = []
        best_by_supplier: dict[str, dict[str, Any]] = {}
        for row in results:
            supplier = row["supplier"]
            score = float(row.get("score", -1.0))
            phrase = str(row.get("phrase") or "")
            support = self.phrase_support_cache.get(phrase)
            if support is None:
                support = self.lexical_support(phrase)
                self.phrase_support_cache[phrase] = support
            combined = 0.75 * score + 0.25 * support

            if debug:
                candidate_rows.append({
                    "supplier": supplier,
                    "phrase": phrase,
                    "score": round(score, 4),
                    "support": round(support, 4),
                    "combined": round(combined, 4),
                })

            enriched = {
                **row,
                "combined": combined,
            }

            passes = score >= threshold or combined >= 0.48
            if passes and (supplier not in best_by_supplier or combined > float(best_by_supplier[supplier].get("combined", -1.0))):
                best_by_supplier[supplier] = enriched

        if not best_by_supplier and results:
            def support_for_phrase(phrase: str) -> float:
                cached_support = self.phrase_support_cache.get(phrase)
                if cached_support is None:
                    cached_support = self.lexical_support(phrase)
                    self.phrase_support_cache[phrase] = cached_support
                return cached_support

            fallback = max(
                results,
                key=lambda item: 0.75 * float(item.get("score", -1.0)) + 0.25 * support_for_phrase(str(item.get("phrase") or "")),
            )
            fallback_score = float(fallback.get("score", -1.0))
            fallback_phrase = str(fallback.get("phrase") or "")
            fallback_support = support_for_phrase(fallback_phrase)
            fallback_combined = 0.75 * fallback_score + 0.25 * fallback_support
            if fallback_score >= 0.40 and fallback_support >= 0.43 and fallback_combined >= 0.43:
                best_by_supplier[fallback["supplier"]] = {
                    **fallback,
                    "combined": fallback_combined,
                }

        supplier_ranking = sorted(best_by_supplier.values(), key=lambda x: float(x.get("combined", x["score"])), reverse=True)
        best = self.resolve_overlapping_suppliers(supplier_ranking)

        payload = {
            "supplier": best["supplier"],
            "supplier_score": round(best["score"], 4) if best["score"] >= 0 else None,
            "matched_supplier_phrase": best.get("phrase"),
        }

        if debug:
            top_candidates = sorted(candidate_rows, key=lambda item: item["combined"], reverse=True)[:8]
            payload["supplier_debug"] = {
                "tokens": tokens,
                "phrases_count": len(phrases),
                "top_candidates": top_candidates,
            }

        return payload

class ExpenseAmountExtractor:
    def __init__(self, suppliers: list[str]) -> None:
        self.model = get_amount_model()

    @staticmethod
    def to_float(value: str) -> Optional[float]:
        cleaned = value.replace(" ", "").replace("\u00A0", "")
        match = re.search(r"\d+(?:[,]\d{1,2})?", cleaned)
        if not match:
            return None
        try:
            return float(match.group(0).replace(",", "."))
        except ValueError:
            return None

    @staticmethod
    def phrase_span(text: str, phrase: Optional[str]) -> Optional[tuple[int, int]]:
        if not phrase:
            return None
        idx = text.lower().find(phrase.lower())
        if idx == -1:
            return None
        return idx, idx + len(phrase)

    @staticmethod
    def overlaps(span1: tuple[int, int], span2: Optional[tuple[int, int]]) -> bool:
        if span2 is None:
            return False
        return span1[0] < span2[1] and span2[0] < span1[1]

    @staticmethod
    def expand_amount_text(text: str, start: int, end: int) -> tuple[str, tuple[int, int]]:
        suffix = re.match(r",\d{1,2}", text[end:])
        if suffix:
          new_end = end + len(suffix.group(0))
          return text[start:new_end].strip(), (start, new_end)

        prefix = re.search(r"(\d{1,3}(?:\s*\d{3})*),", text[:start])
        if prefix:
          new_start = prefix.start(1)
          return text[new_start:end].strip(), (new_start, end)

        return text[start:end].strip(), (start, end)

    def extract(
        self,
        text: str,
        matched_date_phrase: Optional[str] = None,
        matched_supplier_phrase: Optional[str] = None,
    ) -> dict[str, Any]:
        if self.model is None:
            return {"amount": None, "amount_text": None}

        date_span = self.phrase_span(text, matched_date_phrase)
        supplier_span = self.phrase_span(text, matched_supplier_phrase)
        entities = self.model.predict_entities(text, ["money"], threshold=0.3)

        for ent in sorted(entities, key=lambda item: float(item.get("score", 0.0)), reverse=True):
            raw_span = (int(ent.get("start", 0)), int(ent.get("end", 0)))
            amount_text, span = self.expand_amount_text(text, raw_span[0], raw_span[1])
            amount = self.to_float(amount_text)
            overlaps_date = self.overlaps(span, date_span)
            overlaps_supplier = self.overlaps(span, supplier_span)

            if amount is None:
                continue
            if overlaps_date or overlaps_supplier:
                continue
            return {"amount": amount, "amount_text": amount_text}

        return {"amount": None, "amount_text": None}

class ExpenseTextExtractor:
    def __init__(self, suppliers: list[str], users: list[str]) -> None:
        self.date_extractor = ExpenseDateExtractor()
        self.supplier_extractor = ExpenseSupplierExtractor(suppliers=suppliers)
        self.amount_extractor = ExpenseAmountExtractor(suppliers=suppliers)
        self.user_extractor = ExpenseUserExtractor(users=users, suppliers=suppliers, model=get_embedding_model())

    def extract(self, text: str, reference_date: str | date | None = None, debug_supplier: bool = False) -> dict[str, Any]:
        date_info = self.date_extractor.extract(text, reference_date=reference_date)
        supplier_info = self.supplier_extractor.extract(
            text,
            date_phrase=date_info.get("matched_date_phrase"),
            debug=debug_supplier,
        )
        user_info = self.user_extractor.extract(
            text,
            supplier_phrase=supplier_info.get("matched_supplier_phrase"),
            date_phrase=date_info.get("matched_date_phrase"),
        )
        amount_info = self.amount_extractor.extract(
            text,
            matched_date_phrase=date_info["matched_date_phrase"],
            matched_supplier_phrase=supplier_info["matched_supplier_phrase"],
        )

        result = {
            "text": text,
            "user": user_info["user"],
            "supplier": supplier_info["supplier"],
            "amount": amount_info["amount"],
            "date": date_info["date"],
            "date_iso": date_info["date_iso"],
        }
        if debug_supplier and "supplier_debug" in supplier_info:
            result["supplier_debug"] = supplier_info["supplier_debug"]
        return result


def build_default_pipeline(suppliers: list[str], users: list[str]) -> ExpenseTextExtractor:
    return ExpenseTextExtractor(suppliers=suppliers, users=users)


def extract_names(items: Any) -> list[str]:
    if not isinstance(items, list):
        return []

    names: list[str] = []
    for item in items:
        if isinstance(item, dict):
            name = item.get("name")
            if isinstance(name, str) and name.strip():
                names.append(name.strip())
            continue

        if isinstance(item, str) and item.strip():
            names.append(item.strip())

    return names


def read_payload_from_stdin() -> dict[str, Any]:
    raw = sys.stdin.read().strip()
    if not raw:
        return {}

    try:
        payload = json.loads(raw)
    except json.JSONDecodeError as exception:
        raise RuntimeError(f"Invalid JSON payload in stdin: {exception}") from exception

    return payload if isinstance(payload, dict) else {}


def polish_notes_text(text: str) -> str:
    normalized = re.sub(r"\s+", " ", text).strip()
    if not normalized:
        return ""

    normalized = normalized[0].upper() + normalized[1:]
    if normalized[-1] not in ".!?":
        normalized += "."

    return normalized


def transcribe_audio_text(audio_path: str) -> str:
    mock_text = os.getenv("EXPENSE_VOICE_MOCK_TEXT")
    if mock_text:
        return mock_text.strip()

    try:
        whisper_model = get_whisper_model()
        segments, _ = whisper_model.transcribe(audio_path, language="ru", vad_filter=True)
        text = " ".join(segment.text.strip() for segment in segments if segment.text and segment.text.strip())
        if text:
            return text
    except Exception:
        pass

    raise RuntimeError(
        "Speech-to-text backend is unavailable. Install faster-whisper or set EXPENSE_VOICE_MOCK_TEXT."
    )


def process_voice_request(audio_path: str, mode: str, payload: dict[str, Any]) -> dict[str, Any]:
    context = payload.get("context", {}) if isinstance(payload, dict) else {}
    supplier_names = extract_names(context.get("suppliers"))
    user_names = extract_names(context.get("users"))

    transcript = transcribe_audio_text(audio_path)

    if mode == "notes":
        notes = polish_notes_text(transcript)
        return {
            "status": "ok",
            "text": transcript,
            "notes": notes,
            "supplier": None,
            "user": None,
            "date": None,
            "sum": None,
        }

    if not supplier_names:
        raise RuntimeError("No suppliers were provided by Laravel context.")

    if not user_names:
        raise RuntimeError("No users were provided by Laravel context.")

    extractor = build_default_pipeline(suppliers=supplier_names, users=user_names)

    extracted = extractor.extract(transcript, reference_date=date.today().isoformat())

    return {
        "status": "ok",
        "text": transcript,
        "notes": polish_notes_text(extracted.get("text") or transcript),
        "supplier": extracted.get("supplier"),
        "user": extracted.get("user"),
        "date": extracted.get("date_iso") or extracted.get("date"),
        "sum": extracted.get("amount"),
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Expense voice transcription bridge")
    parser.add_argument("--audio-path", required=True, help="Absolute path to recorded audio file")
    parser.add_argument("--mode", required=True, choices=["expense", "notes"], help="Transcription mode")

    return parser.parse_args()


def main() -> None:
    args = parse_args()

    if not os.path.isfile(args.audio_path):
        raise RuntimeError(f"Audio file does not exist: {args.audio_path}")

    payload = read_payload_from_stdin()
    response = process_voice_request(args.audio_path, args.mode, payload)

    print(json.dumps(response, ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as exception:
        print(str(exception), file=sys.stderr)
        sys.exit(1)
