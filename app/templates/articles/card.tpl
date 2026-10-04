<article class="article-card">
  <a class="article-card__link" href="/article?id={$article->id}">
    <img class="article-card__image" src="/{$article->imagePath}" alt=""
         width="960" height="540" loading="lazy" decoding="async">
    <h3 class="article-card__title">{$article->title}</h3>
  </a>
  {if $article->description !== ''}
    <p class="article-card__description">{$article->description}</p>
  {/if}
  <div class="article-card__meta">
    <time datetime="{$article->publishedAt->format('c')}">{$article->publishedAt->format('d.m.Y')}</time>
    <span>Просмотры: {$article->views}</span>
  </div>
</article>
