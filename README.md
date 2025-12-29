## Testovoe Todo API

Небольшой REST API для **управления задачами (todo)** с авторизацией через **Laravel Sanctum**.

### Стек

- **Backend**: PHP **8.2+**, Laravel **12**
- **Auth**: Laravel **Sanctum** (Bearer token)
- **DB**: **MySQL** (через `DB_CONNECTION=mysql` в `.env`)
- **Тесты**: PHPUnit (Feature tests)

### Что сделано (по факту в коде)

- **Регистрация / логин / логаут** с выдачей Sanctum-токена
- **CRUD задач** (создание, просмотр, обновление, удаление)
- **Привязка задач к пользователю** (`tasks.user_id`)
- **Авторизация**: доступ к задаче только у владельца (Policy)
- **Soft delete** для задач (DELETE не удаляет запись физически)
- **Валидация** через FormRequest + единый JSON-формат ошибок (422)
- **Пагинация** списка задач с `per_page` в диапазоне **1..100** (по умолчанию 20)
- **Seeder**: генерация 50 задач через фабрику
- **Postman коллекция**: `todo.postman_collection.json`

### Модель Task (поля)

- **id**: int
- **user_id**: int (владелец)
- **title**: string (required)
- **description**: text (nullable)
- **status**: enum (string)
- **created_at / updated_at**
- **deleted_at** (soft delete)

Допустимые значения `status`:
- `draft`
- `new`
- `in_progress`
- `paused`
- `done`
- `canceled`

### Как запустить (локально)

**Требования**

- PHP **8.2+**
- PHP extension: **pdo_mysql** (для MySQL)
- Composer
- Node.js + npm (для сборки ассетов; для API не строго обязательно, но в проекте есть Vite/Tailwind)

**Быстрый старт (самый простой)**

Перед запуском убедись, что в `.env` выставлены настройки MySQL (см. раздел **База данных** ниже).

```bash
composer run setup
php artisan serve
```

Команда `composer run setup` сделает:
- `composer install`
- скопирует `.env.example` → `.env`
- `php artisan key:generate`
- `php artisan migrate --force`
- `npm install`
- `npm run build`

**Альтернатива (dev-режим)**

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
composer run dev
```

`composer run dev` поднимает: сервер, очередь, логи и Vite (через `concurrently`).

**База данных**

Проект поддерживает разные драйверы БД, но если ты используешь **MySQL**, достаточно настроить `.env` и прогнать миграции.

**MySQL**

1) Создай базу данных (пример): `testovoe_todo`

2) В `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=testovoe_todo
DB_USERNAME=root
DB_PASSWORD=
```

3) Миграции/сид:

```bash
php artisan migrate --seed
```

**SQLite (опционально, если нужно очень быстро поднять без MySQL)**  
Если хочешь использовать SQLite, выставь `DB_CONNECTION=sqlite` и создай файл:

```bash
touch database/database.sqlite
php artisan migrate --seed
```

### Как проверить API

Базовый URL по умолчанию: `http://127.0.0.1:8000`

Все защищённые эндпоинты требуют заголовок:

- **Authorization**: `Bearer <token>`
- **Accept**: `application/json`

Токен приходит в ответе на регистрацию/логин.

### API эндпоинты

**Auth**

- **POST** `/api/auth/register` — регистрация
  - **Body (JSON)**:
    - `name` (string, required)
    - `email` (string/email, required, unique)
    - `password` (string, required, min 6)
    - `password_confirmation` (string, required)
  - **201 Created**:
    - `id`, `name`, `email`, `token`

- **POST** `/api/auth/login` — логин
  - **Body (JSON)**:
    - `email` (string/email, required)
    - `password` (string, required)
  - **200 OK**:
    - `id`, `name`, `email`, `token`

- **GET** `/api/user` — текущий пользователь (требует токен)
  - **200 OK**:
    - `id`, `name`, `email`

- **POST** `/api/auth/logout` — логаут (требует токен, удаляет текущий access token)
  - **200 OK**:
    - `{ "message": "Logged out" }`

**Tasks (требует токен)**

- **GET** `/api/tasks` — список задач текущего пользователя (пагинация)
  - **Query**:
    - `per_page` (1..100, default 20)
  - **200 OK**: Laravel pagination JSON, где `data` содержит массив задач

- **POST** `/api/tasks` — создать задачу
  - **Body (JSON)**:
    - `title` (string, required, max 255)
    - `description` (string, nullable, max 20000)
    - `status` (string, nullable; если не передан — будет `new`)
  - **201 Created**: payload задачи

- **GET** `/api/tasks/{task}` — получить задачу
  - **Доступ**: только владелец, иначе **403**

- **PUT/PATCH** `/api/tasks/{task}` — обновить задачу (частичное обновление разрешено)
  - **Body (JSON)**:
    - `title` / `description` / `status` (любые из)
  - **Важно**: попытка поменять `user_id` игнорируется на уровне контроллера

- **DELETE** `/api/tasks/{task}` — удалить задачу (soft delete)
  - **200 OK**:
    - `{ "message": "Deleted" }`

### Формат ошибок

- **401 Unauthorized** — нет/невалидный токен
- **403 Forbidden** — попытка доступа к чужой задаче
- **422 Validation error** — ошибки валидации, формат:
  - `message`: `"Validation error"`
  - `errors`: объект с полями и массивами сообщений

### Примеры запросов (curl)

Регистрация:

```bash
curl -X POST "http://127.0.0.1:8000/api/auth/register" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"name":"Demo","email":"demo@example.com","password":"qwerty123","password_confirmation":"qwerty123"}'
```

Создание задачи:

```bash
curl -X POST "http://127.0.0.1:8000/api/tasks" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"title":"My task","description":"Some text","status":"in_progress"}'
```

### Postman

Импортируй файл **`todo.postman_collection.json`**, затем:
- задай переменную коллекции **`url`** (например `http://127.0.0.1:8000`)
- установи **Bearer Token** (токен возьми из ответа `register`/`login`)

### Тесты

```bash
composer test
```
