# Инструкция по аутентификации в GitHub

## Вариант 1: Personal Access Token (PAT) - Рекомендуется

### Шаг 1: Создание токена
1. Откройте [GitHub.com](https://github.com) и войдите
2. Нажмите на ваш аватар (правый верхний угол) → **Settings**
3. В левом меню внизу найдите **Developer settings**
4. Выберите **Personal access tokens** → **Tokens (classic)**
5. Нажмите **Generate new token** → **Generate new token (classic)**
6. Заполните форму:
   - **Note**: `PC Salon Project` (любое название)
   - **Expiration**: выберите срок (например, 90 days)
   - **Select scopes**: отметьте **`repo`** (полный доступ к репозиториям)
7. Нажмите **Generate token** внизу
8. **ВАЖНО**: Скопируйте токен сразу! Он показывается только один раз.

### Шаг 2: Использование токена
При выполнении `git push`:
- **Username**: ваш GitHub username (`WhiteHat5`)
- **Password**: вставьте скопированный токен (не ваш пароль от GitHub!)

```bash
git push -u origin main
```

### Шаг 3: Сохранение учетных данных (опционально)
Чтобы не вводить токен каждый раз:

**Windows (Git Credential Manager):**
```bash
git config --global credential.helper wincred
```

После первого успешного push токен сохранится.

---

## Вариант 2: SSH ключи

### Шаг 1: Проверка существующих SSH ключей
```bash
ls -al ~/.ssh
```

Если видите файлы `id_rsa.pub` или `id_ed25519.pub`, у вас уже есть ключ.

### Шаг 2: Создание нового SSH ключа (если нет)
```bash
ssh-keygen -t ed25519 -C "ваш-email@example.com"
```

Нажмите Enter для всех вопросов (или введите пароль для ключа).

### Шаг 3: Добавление ключа в SSH-агент
```bash
# Запуск SSH-агента
eval "$(ssh-agent -s)"

# Добавление ключа
ssh-add ~/.ssh/id_ed25519
```

### Шаг 4: Копирование публичного ключа
```bash
cat ~/.ssh/id_ed25519.pub
```

Скопируйте весь вывод (начинается с `ssh-ed25519...`).

### Шаг 5: Добавление ключа в GitHub
1. GitHub → Settings → **SSH and GPG keys**
2. Нажмите **New SSH key**
3. **Title**: `PC Salon` (любое название)
4. **Key**: вставьте скопированный ключ
5. Нажмите **Add SSH key**

### Шаг 6: Переключение на SSH URL
```bash
git remote set-url origin git@github.com:WhiteHat5/pc_salon.git
git push -u origin main
```

---

## Решение проблем

### Если токен не работает:
- Убедитесь, что скопировали весь токен
- Проверьте, что выбрали scope `repo`
- Токен должен быть активным (не истек)

### Если SSH не работает:
```bash
# Проверка подключения
ssh -T git@github.com
```

Должно вывести: `Hi WhiteHat5! You've successfully authenticated...`

### Альтернатива: GitHub Desktop
Можно использовать [GitHub Desktop](https://desktop.github.com/) - он автоматически обрабатывает аутентификацию.

