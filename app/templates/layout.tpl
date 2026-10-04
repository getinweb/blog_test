<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title} — Блог</title>
  <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
  <a class="skip-link" href="#main-content">К содержимому</a>
  <header class="site-header">
    <div class="page-container">
      <a class="site-header__brand" href="/" aria-label="Блог — главная">Блог</a>
    </div>
  </header>
  <main class="page-container" id="main-content" tabindex="-1">
    {block name='content'}{/block}
  </main>
</body>
</html>
