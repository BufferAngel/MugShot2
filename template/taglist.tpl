<ul id='mugshot-tags' class='mugshot-tag-list'>
{foreach from=$MUGSHOT_TAG_LIST item=t}
  <li onclick="MugShot.setText(this)">{$t}</li>
{/foreach}
</ul>
