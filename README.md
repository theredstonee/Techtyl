<p align="center">
  <img src=".github/banner.png" alt="Techtyl, a theme for Pterodactyl and Blueprint" width="100%">
</p>

# Techtyl

Techtyl is a theme and extension for the [Pterodactyl](https://pterodactyl.io) panel, built on the [Blueprint](https://blueprint.zip) framework. It replaces the stock look of the client area, the admin area and the sign-in pages, and it lets the people who use the panel decide how it looks instead of fixing one design for everyone.

The project is in early development. The sections below describe what Techtyl is meant to be and say plainly which parts already work.

## What Techtyl does

**A design you change yourself.** Techtyl ships four colour schemes, from a dark petrol tone to a light paper look and a translucent glass style. On top of the scheme you choose the accent colour, how round the corners are, whether the navigation sits in a wide sidebar, a narrow icon rail or a bar across the top, and how much the interface moves. Changes apply at once, without rebuilding the panel.

**Your own backgrounds and server banners.** An administrator sets a default background and a default banner for each type of server. Every user may replace both with their own pictures, as far as the administrator allows it. Uploaded pictures are checked and re-encoded on the server before they are stored.

**A server list that stays readable.** Servers appear as cards with their banner, their state and their load in percent. The list can be shown as a grid, as rows or in a compact form, and servers can be sorted into folders.

**An admin area in the same style.** The administration pages follow the chosen scheme, so moving between the client area and the admin area no longer feels like switching products.

**Tools that belong in the panel.** Techtyl is planned to bring the features that otherwise need a collection of separate extensions: a browser for plugins, mods and modpacks, a version changer, player and world management, a support ticket system, billing through Stripe, sign-up with e-mail confirmation and social sign-in, and an assistant that can explain what a server is doing.

## Status

| Part | State |
|---|---|
| Design settings (scheme, accent, corners, navigation, motion) | Working |
| Applying the design without a rebuild | Working |
| Background and banner uploads, folders, per-user settings | In progress |
| Restyled client area, sign-in pages and admin area | In progress |
| Full-page designer with live preview | Planned |
| Plugins, mods, modpacks, versions, players, worlds | Planned |
| Tickets, billing, sign-up and social sign-in, assistant | Planned |

## Requirements

- Pterodactyl Panel 1.15.x
- Blueprint `beta-2026-08`

## Installation

Techtyl has no packaged release yet. To try the current state on a panel with Blueprint in developer mode, copy this repository into the `.blueprint/dev` folder of the panel and build it:

```sh
blueprint -build
```

The settings then live under `/admin/extensions/techtyl`.

Use a test panel for this. The extension is not ready for a panel that people depend on.

## Repository layout

| Path | Contents |
|---|---|
| `conf.yml` | Blueprint configuration |
| `app/` | Server-side logic: design values, limits, image handling |
| `admin/` | Settings page, controller, admin stylesheet and wrapper |
| `dashboard/` | Client stylesheet and wrapper |
| `routes/`, `migrations/` | API routes and database tables |
| `public/` | Files served to the browser, including the bundled fonts |
| `eggs/` | Server templates that ship with Techtyl |

## Fonts

Techtyl bundles Bricolage Grotesque and IBM Plex, both under the SIL Open Font License. The licence texts are in `public/fonts/`.
