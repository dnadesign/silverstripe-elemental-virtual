<% if $LinkedElement %>
<div class="element element--virtual $LinkedElement.SimpleClassName.LowerCase<% if $LinkedElement.StyleVariant %> $LinkedElement.StyleVariant<% end_if %><% if $LinkedElement.ExtraClass %> $LinkedElement.ExtraClass<% end_if %>" id="{$LinkedElement.Anchor}">
    $Element
</div>
<% end_if %>
