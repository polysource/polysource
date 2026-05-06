# Launch — v0.1.0 announcements

Two announcement drafts targeting two distinct audiences, per
[ADR-012 — dual-product positioning](../adr/0012-dual-product-positioning.md).
Spaced 1-2 weeks apart so each headline lands cleanly:

- [Announcement A — EasyAdmin audience](./v0.1.0-easyadmin-audience.md)
  → Lead with the **filter bridge** as a drop-in EA enhancement.
- [Announcement B — non-Doctrine admin audience](./v0.1.0-non-doctrine-audience.md)
  → Lead with **Polysource Admin** as the answer to "I don't have a
  Doctrine entity but I still need an admin UI."

## Pre-launch checklist

- [ ] All 14 screenshots regenerated via `make screenshots`
- [ ] CHANGELOG.md updated for v0.1.0
- [ ] Git tag `v0.1.0` annotated + pushed
- [ ] Packagist webhook fired — verify the 6 v0.1.0-pinned packages
      are live: `core`, `filter`, `twig-theme`, `symfony-bundle`,
      `adapter-messenger`, `easyadmin-filter-bridge`
- [ ] GitHub release notes published with the showcase tour link
- [ ] `examples/showcase-demo/` README polished, screenshots embedded
- [ ] `docs/user/showcase-tour.md` proofread end-to-end
- [ ] 3-5 GitHub issues opened with `help wanted` labels for v0.2
      contributions

## Day-of checklist (for each announcement)

- [ ] Tweet/X post at 13:00 UTC (Tuesday/Wednesday best for
      developer audiences)
- [ ] r/symfony post 30 min later (avoid being seen as cross-promo)
- [ ] Mastodon post 1h later (#symfony, #php tags)
- [ ] Symfony Insider submission (deadline-bound)
- [ ] DM template sent to 5-10 hand-picked Symfony champions
- [ ] Monitor for first questions → respond within 30 min
