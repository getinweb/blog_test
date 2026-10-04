{extends file='layout.tpl'}

{block name='content'}
  <p class="category-back"><a href="/">← Все категории</a></p>
  <header class="page-heading">
    <h1>{$category->name}</h1>
    {if $category->description !== ''}
      <p>{$category->description}</p>
    {/if}
  </header>

  <section aria-labelledby="category-articles">
    <div class="category-toolbar">
      <div>
        <h2 id="category-articles">Статьи</h2>
        <p class="category-toolbar__count">Всего: {$totalArticles}</p>
      </div>
      {if $totalArticles > 0}
        <form class="category-sort" action="/category" method="get">
          <input type="hidden" name="id" value="{$category->id}">
          <label for="category-sort">Сортировка</label>
          <select id="category-sort" name="sort">
            <option value="date"{if $sort === 'date'} selected{/if}>По дате публикации</option>
            <option value="views"{if $sort === 'views'} selected{/if}>По просмотрам</option>
          </select>
          <button class="button-link" type="submit">Применить</button>
        </form>
      {/if}
    </div>

    {if $articles !== []}
      <div class="article-grid">
        {foreach $articles as $article}
          {include file='articles/card.tpl' article=$article}
        {/foreach}
      </div>
    {else}
      <p class="empty-state">В этой категории пока нет статей.</p>
    {/if}

    {if $totalPages > 1}
      <nav class="pagination" aria-label="Страницы статей">
        {if $page > 1}
          <a class="button-link" rel="prev" href="/category?id={$category->id}&amp;sort={$sort}&amp;page={$page - 1}">Назад</a>
        {else}
          <span class="pagination__disabled" aria-disabled="true">Назад</span>
        {/if}
        <span class="pagination__current">Страница {$page} из {$totalPages}</span>
        {if $page < $totalPages}
          <a class="button-link" rel="next" href="/category?id={$category->id}&amp;sort={$sort}&amp;page={$page + 1}">Далее</a>
        {else}
          <span class="pagination__disabled" aria-disabled="true">Далее</span>
        {/if}
      </nav>
    {/if}
  </section>
{/block}
