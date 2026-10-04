{extends file='layout.tpl'}

{block name='content'}
  <article class="article-detail">
    <header class="article-detail__header">
      <nav class="article-detail__categories" aria-label="Категории статьи">
        <ul>
          {foreach $article->categories as $category}
            <li><a href="/category?id={$category->id}">{$category->name}</a></li>
          {/foreach}
        </ul>
      </nav>
      <h1>{$article->title}</h1>
      {if $article->description !== ''}
        <p class="article-detail__description">{$article->description}</p>
      {/if}
      <div class="article-detail__meta">
        <time datetime="{$article->publishedAt->format('c')}">{$article->publishedAt->format('d.m.Y')}</time>
        <span>Просмотры: {$views}</span>
      </div>
    </header>
    <img class="article-detail__image" src="/{$article->imagePath}" alt="{$article->title}"
         width="960" height="540" decoding="async">
    <div class="article-detail__text">{$article->text}</div>
    <footer class="article-detail__footer"><a href="/">Все статьи на главной</a></footer>
  </article>
{/block}
