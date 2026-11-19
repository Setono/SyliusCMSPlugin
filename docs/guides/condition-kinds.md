# Adding a condition kind

A condition kind is one kind of [visibility condition](../../GLOSSARY.md#visibility), such as "customer group" or
"product". Editors add conditions of it to a block, so the block shows only when they match, and to a placement's
[page scope](../../GLOSSARY.md#shop-pages). A project adds its own as a service with `#[AsConditionKind]` that implements
`Setono\SyliusCMSPlugin\Condition\ConditionKindInterface`.

This guide adds "Day of week", so a block shows on some days only.

## The parameters

```php
// src/CMS/Condition/DayOfWeekParams.php
namespace App\CMS\Condition;

use Setono\SyliusCMSPlugin\Field\Choice;

final class DayOfWeekParams
{
    /** @var list<string> */
    #[Choice(label: 'app.cms.field.day_of_week.days', multiple: true, required: true, choices: [
        'monday' => 'app.cms.day.monday',
        'tuesday' => 'app.cms.day.tuesday',
        'wednesday' => 'app.cms.day.wednesday',
        'thursday' => 'app.cms.day.thursday',
        'friday' => 'app.cms.day.friday',
        'saturday' => 'app.cms.day.saturday',
        'sunday' => 'app.cms.day.sunday',
    ])]
    public array $days = [];
}
```

A `Choice` field whose choices come from the store, such as the shop's channels, names a service implementing
`Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface` in `choicesProvider:` instead of listing `choices`.

## The condition kind

```php
// src/CMS/Condition/DayOfWeekKind.php
namespace App\CMS\Condition;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Condition\TimeDependentConditionKindInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

#[AsConditionKind(
    code: 'app_day_of_week',
    label: 'app.cms.condition_kind.day_of_week',
    reads: [Reads::Time],
    params: DayOfWeekParams::class,
)]
final class DayOfWeekKind implements TimeDependentConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        if (!$params instanceof DayOfWeekParams) {
            throw new \InvalidArgumentException(sprintf('The day of week condition takes %s, got %s', DayOfWeekParams::class, $params::class));
        }

        return in_array(strtolower($context->now->format('l')), $params->days, true);
    }

    /**
     * The answer can change at the next midnight
     */
    public function nextChange(object $params, \DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->setTime(0, 0)->add(new \DateInterval('P1D'));
    }
}
```

- **`matches()` answers "is one of"** for the parameters editors filled in. The plugin provides "is not one of" by
  negating it, so a kind never does.
- **Read the shopper, the channel, the locale and the time only from the `RenderContext`**: `$context->channel`,
  `localeCode`, `customer`, `customerGroup` and `now`. The builder's preview sets the customer group and the time as
  editors choose in Shown to and Date, so they see what the condition does. The day is `now`'s, in the time zone PHP
  runs in.
- **The host-page context** comes from `$context->get('product')` and the like. List the keys the kind needs in
  `needs: ['product']`: it's then offered only where they're available, and without one the condition never matches.

`#[AsConditionKind]` options:

| Option | Meaning |
| --- | --- |
| `code`, `label` | A unique code, prefixed such as `app_day_of_week`, and a translation key. The code is stored in content. |
| `needs` | The context keys it needs. |
| `reads` | What it reads, as `Setono\SyliusCMSPlugin\Condition\Reads` cases: `HostPage`, `Channel`, `Locale`, `Customer` and `Time`. Declare it: a later cache treats a kind that hasn't as uncacheable. `[]` claims it reads nothing. |
| `params` | Its parameters class. Without one, `matches()` gets a `Setono\SyliusCMSPlugin\Attribute\NoParams`. |
| `matches`, `matchesNot` | Translation keys for how the builder words the condition and its negation, "is one of" and "is not one of" by default. |

A kind that reads `Time` implements `TimeDependentConditionKindInterface`, whose `nextChange()` says when its answer can
next change, or `null` when it can't.

## Labels

```yaml
# translations/messages.en.yaml
app:
    cms:
        condition_kind:
            day_of_week: Day of week
        field:
            day_of_week:
                days: Days
        day:
            monday: Monday
            # ...
```

The shipped kinds are listed in [Targeting](../spec/targeting.md#condition-kinds), and the test application's own, with a
product parameter, is `tests/Application/Condition/ProductAvailableKind.php`.
