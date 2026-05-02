# Attributions

The Polysource Twig theme was inspired by — and is functionally compatible
with — the templates that ship with [EasyAdminBundle v5](https://github.com/EasyCorp/EasyAdminBundle),
authored by [EasyCorp](https://easycorp.io/) and the EasyAdmin community.

EasyAdmin v5 templates are distributed under the **MIT License** (the same
license as Polysource), which permits derivative works.

## What was reused

The Polysource theme **does not embed verbatim copies** of EasyAdmin v5
templates. The templates here were rewritten from scratch to consume the
Polysource generic DTOs (`DataPage`, `DataRecord`, `FieldDto`) rather than
EasyAdmin's Doctrine-coupled CRUD context.

What we kept from EasyAdmin's design:

- the high-level information architecture (layout / index / detail / error)
- the per-field template-include pattern with a `generic.html.twig` fallback
- the Bootstrap 5 base styling

## License notice for any future verbatim copies

If a Polysource maintainer copies a specific EasyAdmin v5 template
verbatim, that copy MUST retain the EasyAdmin copyright notice in a
top-of-file comment, per the MIT License. Update this file accordingly.

## Credits

- **EasyCorp** for EasyAdmin v5 — https://github.com/EasyCorp/EasyAdminBundle
- **Bootstrap team** for Bootstrap 5 — https://getbootstrap.com/
