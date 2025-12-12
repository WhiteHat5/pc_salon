# Инструкция по публикации сайта на GitHub Pages

## Шаг 1: Настройка Git (если еще не настроено)

Выполните в терминале (PowerShell или CMD) в папке проекта:

```bash
git config --global user.name "Ваше Имя"
git config --global user.email "ваш-email@example.com"
```

Или для локального репозитория:

```bash
git config user.name "Ваше Имя"
git config user.email "ваш-email@example.com"
```

## Шаг 2: Создание первого коммита

После настройки git выполните:

```bash
cd C:\xampp\htdocs\pc_salon
git add .
git commit -m "Initial commit: PC Salon website"
```

## Шаг 3: Создание репозитория на GitHub

1. Откройте [GitHub.com](https://github.com) и войдите в свой аккаунт
2. Нажмите кнопку **"+"** в правом верхнем углу → **"New repository"**
3. Заполните форму:
   - **Repository name**: `pc_salon` (или другое имя)
   - **Description**: "Интернет-магазин компьютеров"
   - Выберите **Public** (для бесплатного GitHub Pages)
   - **НЕ** ставьте галочки на "Add a README file", "Add .gitignore", "Choose a license" (у нас уже есть эти файлы)
4. Нажмите **"Create repository"**

## Шаг 4: Подключение локального репозитория к GitHub

После создания репозитория GitHub покажет инструкции. Выполните в терминале:

```bash
cd C:\xampp\htdocs\pc_salon
git remote add origin https://github.com/ВАШ-USERNAME/pc_salon.git
git branch -M main
git push -u origin main
```

**Важно**: Замените `ВАШ-USERNAME` на ваш реальный username на GitHub.

Если у вас уже есть коммиты (после шага 2), используйте:
```bash
git push -u origin main
```

Если ветка называется `master` вместо `main`:
```bash
git branch -M main
git push -u origin main
```

## Шаг 5: Настройка GitHub Pages

1. В репозитории на GitHub перейдите в **Settings** (вкладка справа)
2. В левом меню найдите **Pages**
3. В разделе **Source** выберите:
   - **Branch**: `main` (или `master`)
   - **Folder**: `/ (root)`
4. Нажмите **Save**

## Шаг 6: Доступ к сайту

Через 1-2 минуты ваш сайт будет доступен по адресу:
```
https://ВАШ-USERNAME.github.io/pc_salon/
```

**Важно**: Замените `ВАШ-USERNAME` на ваш реальный username.

## Обновление сайта

После внесения изменений в код:

```bash
cd C:\xampp\htdocs\pc_salon
git add .
git commit -m "Описание изменений"
git push
```

Изменения появятся на сайте через 1-2 минуты после `git push`.

## Полезные команды

- Проверить статус: `git status`
- Посмотреть историю: `git log`
- Отменить изменения: `git checkout -- файл.html`
- Посмотреть удаленные репозитории: `git remote -v`

## Решение проблем

### Если не работает HTTPS
Используйте SSH вместо HTTPS:
```bash
git remote set-url origin git@github.com:ВАШ-USERNAME/pc_salon.git
```

### Если нужно изменить имя репозитория
1. В GitHub: Settings → General → Repository name
2. Обновите remote URL:
```bash
git remote set-url origin https://github.com/ВАШ-USERNAME/НОВОЕ-ИМЯ.git
```

### Если сайт не открывается
- Проверьте, что файл `index.html` находится в корне репозитория
- Убедитесь, что в Settings → Pages выбрана правильная ветка
- Подождите 2-3 минуты (GitHub Pages обновляется не мгновенно)

