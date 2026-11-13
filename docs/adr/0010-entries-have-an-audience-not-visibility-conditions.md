# Entries have an audience, not visibility conditions

Blocks carry visibility conditions: any number of rules of any kind, including kinds developers add, stored in the block tree's JSON. Entries don't. An entry has a fixed **audience** instead: everyone, only some customer groups, or everyone except some, where guests and signed-in customers without a group count as groups of their own. The audience is stored as a mode column plus a table of customer group codes beside the entry (codes, as in ADR 0002). Collection listings, sources, menus and URL lookup therefore filter on it in SQL, as they already do on channels, `enabled` and the date window (ADR 0009). An entry outside the shopper's audience behaves like a disabled one everywhere.

We chose this because a blog listing or a "latest posts" source has to leave out what the shopper can't see before it paginates. Conditions stored as JSON could only be checked in PHP after the query, which breaks page sizes and counts. A condition kind a developer adds can't be turned into SQL at all.

## Considered Options

- **A condition list on entries, like blocks have.** This would give one model everywhere, but listings and sources would filter in PHP after paginating.
- **No audience on entries, only conditions on blocks.** A snippet in a capped slot could then never lose to the next placement for a shopper it hides everything from. A wholesale-only page would also answer guests with an empty page instead of a 404.

## Consequences

- Condition kinds that developers add work only on blocks and page scopes. To target an entry by anything other than customer group, editors put conditions on its blocks.
- Host-page conditions never sit on entries. For a snippet in a slot, they belong in the placement's page scope.
- A deleted customer group leaves a dangling code that matches nobody. An "Only these" audience no longer lets anyone in through it, and an "Everyone except these" audience no longer keeps anyone out through it. The admin flags it.
- In a capped slot, a placement whose snippet's audience excludes the shopper doesn't match, so the next placement can win. This is decided without rendering anything.
