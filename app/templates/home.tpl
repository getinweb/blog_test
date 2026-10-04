{extends file='layout.tpl'}

{block name='content'}
  <header class="page-heading">
    <h1>{$title}</h1>
    <p>Последние публикации по темам.</p>
  </header>

  {foreach $sections as $section}
    <section class="category-section" aria-labelledby="category-{$section.category->id}">
      <div class="category-section__heading">
        <div>
          <h2 id="category-{$section.category->id}">{$section.category->name}</h2>
          {if $section.category->description !== ''}
            <p class="category-section__description">{$section.category->description}</p>
          {/if}
        </div>
        <a class="button-link" href="/category?id={$section.category->id}"
           aria-label="Все статьи категории {$section.category->name}">Все статьи</a>
      </div>
      <div class="article-grid">
        {foreach $section.articles as $article}
          {include file='articles/card.tpl' article=$article}
        {/foreach}
      </div>
    </section>
  {foreachelse}
    <div class="empty-state">
      <h2>Статей пока нет.</h2>
      <p>Новые публикации появятся здесь.</p>
    </div>
  {/foreach}
{/block}
