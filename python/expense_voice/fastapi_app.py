from __future__ import annotations

import json
import os
import tempfile
from pathlib import Path

from fastapi import FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse

from expense_voice.transcribe import process_voice_request

app = FastAPI(title="Expense Voice API", version="1.0.0")


@app.exception_handler(HTTPException)
async def http_exception_handler(_, exception: HTTPException) -> JSONResponse:
    message = exception.detail if isinstance(exception.detail, str) else str(exception.detail)

    return JSONResponse(
        status_code=exception.status_code,
        content={
            "status": "error",
            "message": message,
        },
    )


@app.exception_handler(RequestValidationError)
async def validation_exception_handler(_, exception: RequestValidationError) -> JSONResponse:
    return JSONResponse(
        status_code=422,
        content={
            "status": "error",
            "message": str(exception),
        },
    )


def _require_auth(authorization: str | None) -> None:
    expected_token = os.getenv("EXPENSE_VOICE_FASTAPI_TOKEN", "").strip()

    if not expected_token:
        return

    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Missing bearer token.")

    provided_token = authorization.removeprefix("Bearer ").strip()
    if provided_token != expected_token:
        raise HTTPException(status_code=401, detail="Invalid bearer token.")


def _parse_context(context_raw: str | None) -> dict:
    if not context_raw:
        return {}

    try:
        context = json.loads(context_raw)
    except json.JSONDecodeError as exception:
        raise HTTPException(status_code=422, detail=f"Invalid context JSON: {exception}") from exception

    if not isinstance(context, dict):
        raise HTTPException(status_code=422, detail="Context must be a JSON object.")

    return context


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/process-audio")
async def process_audio(
    audio: UploadFile = File(...),
    mode: str = Form(...),
    context: str | None = Form(default=None),
    authorization: str | None = Header(default=None),
) -> dict:
    _require_auth(authorization)

    if mode not in {"expense", "notes"}:
        raise HTTPException(status_code=422, detail="Mode must be either expense or notes.")

    payload = {"context": _parse_context(context)}

    suffix = Path(audio.filename or "voice.webm").suffix or ".webm"
    temp_path: str | None = None

    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as temp_file:
            temp_path = temp_file.name
            temp_file.write(await audio.read())

        result = process_voice_request(audio_path=temp_path, mode=mode, payload=payload)

        if not isinstance(result, dict):
            raise HTTPException(status_code=500, detail="Transcription backend returned invalid response.")

        return result
    except HTTPException:
        raise
    except Exception as exception:
        raise HTTPException(status_code=422, detail=str(exception)) from exception
    finally:
        if temp_path and os.path.exists(temp_path):
            os.unlink(temp_path)
