<select id='mugshot-tags' onchange="MugShot.setText(this)" class='mugshot-tag-list'>
{foreach from=$MUGSHOT_TAG_LIST item=t}
  <option>{$t}</option>
{/foreach}
</select>
