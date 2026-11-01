---
description: DTO conventions for Spatie Laravel Data classes. Use when creating or editing files under app/Dtos.
paths:
  - 'app/Dtos/**/*.php'
applyTo: 'app/Dtos/**/*.php'
---

# DTO Rules

- DTOs are `Spatie\LaravelData\Data` with constructor-promoted camelCase properties, no `@property` docblocks. Input mapping is snake_case → camelCase globally (`config/data.php`), so `XData::from($request->validated())` just works.
- Use DTOs for grouped payloads at action boundaries, not for single models or a couple of obvious arguments.
- Update DTOs hold the bound model plus `Type|Optional` fields and `#[MapOutputName(SnakeCaseMapper::class)]`, so omitted fields stay out of `fill()`.
- Clearable nullable fields are `type|Optional|null`. A plain `?type` hydrates an omitted field as `null` and wipes it on PATCH.
- Nested arrays use `DataCollectionOf` with accurate array annotations.

```php
#[MapOutputName(SnakeCaseMapper::class)]
class UpdateXData extends Data
{
    public function __construct(
        public X $x,
        public string|Optional $name,
        public string|Optional|null $description,
    ) {}
}
```
