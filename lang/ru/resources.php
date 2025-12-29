<?php

return [
    'sections' => [
        'main' => 'Данные',
    ],
    'fields' => [
        'category' => 'Категория',
        'date' => 'Дата',
        'email' => 'Email',
        'created_at' => 'Создан',
        'debtor' => 'Должник',
        'updated_at' => 'Обновлён',
        'email_verified_at' => 'Email подтвержден',
        'image' => 'Изображение',
        'name' => [
            'animate' => 'Имя',
            'inanimate' => 'Название',
        ],
        'notes' => 'Описание',
        'notes_message' => [
            'unpaid' => ':debtor задолжал :creditor :sum MDL',
            'partial' => ':debtor внёс частичную оплату. Остаток долга перед :creditor: :sum MDL',
            'paid' => ':debtor погасил долг перед :creditor на сумму :sum MDL'
        ],
        'password' => 'Пароль',
        'slug' => 'Символьный код',
        'sum' => 'Сумма',
        'debt_sum' => 'Сумма долга',
        'partial_sum' => 'Сумма частичной оплаты',
        'payment_status' => 'Статус оплаты',
        'date_paid' => 'Дата оплаты',
        'supplier' => 'Поставщик',
        'suppliers' => 'Поставщики',
        'user' => 'Пользователь',
        'password' => 'Пароль',
        'password_confirmation' => 'Подтверждение пароля',
        'change_password' => 'Изменить пароль',
        'notes' => 'Описание',
        'payer' => 'Плательщик',
        'overpayment' => ':user переплачивает на:',
    ],
    'notifications' => [
        'create' => [
            'category' => 'Категория успешно создана',
            'expense' => 'Расход успешно создан',
            'supplier' => 'Поставщик успешно создан',
            'user' => 'Пользователь успешно создан',
        ],
        'edit' => [
            'category' => 'Категория успешно отредактирована',
            'expense'  => 'Расход успешно отредактирован',
            'supplier' => 'Поставщик успешно отредактирован',
            'user'  => 'Пользователь успешно отредактирован',
        ],
        'delete' => [
            'category' => 'Категория успешно удалена',
            'expense'  => 'Расход успешно удалён',
            'supplier' => 'Поставщик успешно удалён',
            'user'  => 'Пользователь успешно удалён',
        ],
        'skip' => [
            'create' => 'Перевод для создания ресурса ":resourceName" не найден.',
            'edit' => 'Перевод для редактирования ресурса ":resourceName" не найден.',
            'categories' => 'Категория с таким названием не существует!',
            'users' => 'Пользователь с таким названием не существует!',
            'suppliers' => 'Поставщик с таким названием не существует!',
        ],
        'load' => [
            'categories' => 'Загрузка категории...',
            'users' => 'Загрузка пользователей...',
            'suppliers' => 'Загрузка поставщиков...',
        ],
        'warn' => [
            'expense' => [
                'title' => 'Внимание',
                'body' => 'Поставщик не связан с выбранной категорией и был удалён из формы.',
            ],
            'debt' => [
                'title' => 'Внимание',
                'body' => 'Сумма частичной оплаты не может быть равной или превышать сумму долга.',
            ],
        ],
    ],
    'search_placeholder' => [
        'resource' => [
            'expense' => '(Поставщик, Описание)',
            'supplier' => '(Поставщик)',
            'category' => '(Категория, Описание)',
            'user' => '(Пользователь, Почта)',
        ],
        'missing' => 'Заполнитель не найден!',
    ],
    'buttons' => [
        'edit' => 'Редактировать',
        'delete' => 'Удалить',
        'create_another' => 'Сохранить и создать ещё',
        'copy' => 'Копировать',
        'pay_off_debt' => 'Погасить долг',
    ],
    'toggleButtons' => [
        'options' => [
            'unpaid' => 'Не оплачено',
            'partial' => 'Частично оплачено',
            'paid' => 'Полностью оплачено',
        ],
        'color' => [
            'unpaid' => 'background-color: #fee2e2; border-radius: 6px;',
            'partial' => 'background-color: #fef9c3; border-radius: 6px;',
            'paid' => 'background-color: #dcfce7; border-radius: 6px;',
        ],
    ],
    'filters' => [
        'date_from' => 'Начало периода: ',
        'date_until' => 'Конец периода: ',
        'sum_min' => 'Минимальная сумма: ',
        'sum_max' => 'Максимальная сумма: ',
    ],
];
