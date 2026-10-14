# Silverstripe Elemental Virtual

[![CI](https://github.com/dnadesign/silverstripe-elemental-virtual/actions/workflows/ci.yml/badge.svg)](https://github.com/dnadesign/silverstripe-elemental-virtual/actions/workflows/ci.yml)

## Introduction

This module extends [Elemental](https://github.com/silverstripe/silverstripe-elemental)
so that a single content block can be shown on more than one page.

Instead of copying a block, you add a **Virtual block** to another page and
point it at the original. The virtual block renders the original block's
content, so you only need to edit the original once and every page that links
to it is updated.

## Requirements

| Elemental Virtual | Silverstripe CMS | Elemental |
| ----------------- | ---------------- | --------- |
| 3.x               | 6.x              | 6.x       |
| 2.x               | 5.x              | 5.x       |
| 1.x               | 4.x              | 3.x, 4.x  |

## Installation

```sh
composer require dnadesign/silverstripe-elemental-virtual
```

Then build the database and flush:

```sh
vendor/bin/sake db:build --flush
```

## Usage

1. **Make a block available.** Open the original block, go to the
   **Settings** tab and tick **Available globally**. You can only link to
   blocks that have this setting.
2. **Link to it from another page.** On the other page, add a **Virtual block**
   and pick the original block from the **Linked element** dropdown.
3. **Publish.** Publish both the original block and the page with the virtual
   block. If the original block isn't published, the virtual block shows a
   warning in the CMS and doesn't render on the live site.

To change the content, edit the original block. The virtual block has a
"Click here to edit the original" link that takes you there.

### Finding blocks in the dropdown

Each block has a **Virtual Lookup Title**. The **Linked element** dropdown
shows and searches this title. It's regenerated every time the block is
saved and looks like this:

```
Contact details (Content #42 - used on About us)
```

That is the block's title, type, ID and the page it's on. A block without a
title shows `[no title]`, and a block that isn't on any page shows
`not currently in use`. Both kinds are sorted to the bottom of the list.

You can see a block's lookup title on its **Settings** tab.

### Which pages use a block?

When a block has virtual copies, the original block gets a **Linked To** tab
that lists every page showing it. Blocks that are edited inline
(`inline_editable: true`, the Elemental default) don't get this tab, because
inline-editable blocks can't contain a GridField.

### Deleting the original block

If you delete an original block that has virtual copies from the draft stage,
the module keeps the content. It replaces the first virtual copy with a real
copy of the original, choosing a published virtual copy if there is one. All
other virtual copies are then pointed at the new block.

## Configuration

### Default "Available globally" setting

New blocks are not available globally by default. You can change this for all
blocks or only for some block types:

```yml
DNADesign\Elemental\Models\BaseElement:
  default_global_elements: true

# Override for a single block type
App\Elements\BannerBlock:
  default_global_elements: false
```

This setting only applies to **new** blocks. To apply it to existing blocks,
run the reset task. The task sets `AvailableGlobally` on every block, on both
the draft and live stages, to the value configured for its class:

```sh
vendor/bin/sake tasks:elemental-virtual-reset-globally-enabled
```

The task overwrites any changes editors have made by hand.

### Dropdown label field

The **Linked element** dropdown uses `VirtualLookupTitle` for its labels and
search. To use a different database field on `BaseElement`:

```yml
DNADesign\ElementalVirtual\Model\ElementVirtual:
  linkable_title_field: Title
```

### Customising the lookup title

To change the lookup title, add an extension to `BaseElement` that implements
`updateVirtualLinkedSummary`:

```php
namespace App\Extensions;

use SilverStripe\Core\Extension;

class VirtualSummaryExtension extends Extension
{
    protected function updateVirtualLinkedSummary(string &$summary): void
    {
        $summary = 'Shared: ' . $summary;
    }
}
```

```yml
DNADesign\Elemental\Models\BaseElement:
  extensions:
    - App\Extensions\VirtualSummaryExtension
```

Existing lookup titles change the next time each block is saved. Globally
available blocks that don't have a lookup title yet get one on `db:build`.

## Templates and front end

A virtual block renders the linked block's own template, wrapped in the
`DNADesign/Elemental/Layout/ElementHolder_VirtualLinked` holder. The holder
adds an `element--virtual` CSS class and uses the original block's anchor as
its `id`. To change the wrapper markup, override that template in your theme.

Blocks that have their own controller actions, such as forms, also work
through a virtual block. The module adds an `element/$ID` URL handler to
`ContentController`. `ElementVirtualLinkedController` then sends those
actions, and the redirects that follow them, to the page that shows the
virtual block.

## Translations

Translations are in `lang/` and are managed with the usual Silverstripe i18n
workflow. Pull requests for new languages are welcome.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md). To run the checks locally:

```sh
vendor/bin/phpcs
vendor/bin/phpunit
```
