![Leaderboard](https://placeholder.img/leaderboard-banner.jpg)

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md) [![Latest Stable Version](https://img.shields.io/packagist/v/huseyinfiliz/leaderboard.svg)](https://packagist.org/packages/huseyinfiliz/leaderboard) [![Total Downloads](https://img.shields.io/packagist/dt/huseyinfiliz/leaderboard.svg)](https://packagist.org/packages/huseyinfiliz/leaderboard)

# Leaderboard

A points-based leaderboard extension for [Flarum](https://flarum.org) forums. Award points for community activity, display rankings with a beautiful podium, and motivate engagement through configurable point sources and time-based filters.

### 🏆 Podium & Rankings

![Podium Demo](https://placeholder.img/leaderboard-podium.png)

### 📊 Top Contenders & Honorable Mentions

![Contenders Demo](https://placeholder.img/leaderboard-contenders.png)

### ⚙️ Admin Panel

![Admin Demo](https://placeholder.img/leaderboard-admin.png)

## Features

- 🏆 **Podium Display**: Top 3 users shown in a gold/silver/bronze podium with avatars and stats
- 🔥 **Top Contenders**: Ranks #4-#10 displayed in a responsive card grid
- 📋 **Honorable Mentions**: Compact two-column list for remaining users with infinite scroll
- ⏰ **Period Filters**: Daily, weekly, monthly, quarterly, yearly, and all-time rankings
- ⭐ **Configurable Points**: Set point values for each activity type independently
- 🎯 **11 Point Sources**: Discussions, posts, daily login, likes (given/received), reactions (given/received), best answers, badges, and votes
- 🏷️ **Tag Exclusions**: Exclude discussions with specific tags from earning points
- 👥 **Group Exclusions**: Hide users in selected groups from the leaderboard
- 🔄 **Maintenance Tools**: Full recalculation and lightweight sync for point adjustments
- 🦴 **Skeleton Loading**: Smooth loading experience with animated placeholders
- 📱 **Responsive Design**: Optimized layout for mobile, tablet, and desktop
- 🃏 **User Card Integration**: Show leaderboard points on user cards throughout the forum

## Installation

```bash
composer require huseyinfiliz/leaderboard:"*"
```

You can also install with Extension Manager: `huseyinfiliz/leaderboard`

## Updating

```bash
composer update huseyinfiliz/leaderboard
php flarum migrate
php flarum cache:clear
```

To remove simply run `composer remove huseyinfiliz/leaderboard`.

## Quick Start

### For Users

1. Navigate to the **Leaderboard** page from the sidebar
2. Use **period pills** to filter rankings (Daily, Weekly, Monthly, etc.)
3. Click on any user card to visit their profile
4. Your points are visible on your user card across the forum

### For Admins

Navigate to **Admin → Leaderboard** to configure the extension. The admin panel is organized into four tabs:

#### General Tab

- **Leaderboard Name**: Customize the page title displayed in the sidebar and header
- **Points Label**: Set the label shown next to point values (e.g., "Points", "XP", "Karma")

#### Points Tab

Configure point values for each activity type. Points are organized into collapsible sections:

| Section | Activities | Default |
|---------|-----------|---------|
| **Core** | Discussion started, Post created, Daily login | 1 each |
| **Likes** | Like received, Like given | 1, 0 |
| **Reactions** | Reaction received, Reaction given | 1, 0 |
| **Best Answer** | Best answer selected | 2 |
| **Badges** | Badge earned | 3 |
| **Gamification** | Upvote received, Downvote received | 1, -1 |

> **Tip**: Set a point value to `0` to disable that source. Negative values (e.g., downvotes) deduct points.

#### Exclusions Tab

- **Excluded Groups**: Select user groups to hide from the leaderboard (e.g., Admins, Bots)
- **Excluded Tags**: Select tags whose discussions won't earn points (requires `flarum/tags`)

#### Maintenance Tab

| Action | Description |
|--------|-------------|
| **Recalculate All Activity** | Full rescan of all source data. Re-creates point records from scratch. Use after changing tag exclusions or if data seems out of sync. |
| **Sync Points** | Lightweight recalculation of totals using current point values. Use after changing point values. |

> **Note**: Do not close the page during a full recalculation — the operation will fail if interrupted.

## Optional Integrations

The leaderboard automatically integrates with these extensions when they are enabled:

| Extension | Point Sources |
|-----------|--------------|
| [`flarum/likes`](https://github.com/flarum/likes) | Like received, Like given |
| [`flarum/tags`](https://github.com/flarum/tags) | Tag-based exclusions |
| [`fof/reactions`](https://github.com/FriendsOfFlarum/reactions) | Reaction received, Reaction given |
| [`fof/best-answer`](https://github.com/FriendsOfFlarum/best-answer) | Best answer selected |
| [`fof/badges`](https://github.com/FriendsOfFlarum/badges) | Badge earned |
| [`fof/gamification`](https://github.com/FriendsOfFlarum/gamification) | Upvote received, Downvote received |
| [`flarum/approval`](https://github.com/flarum/approval) | Content restored event support |

No configuration is needed — install the extension and points will be awarded automatically based on your point settings.

## 🔧 Advanced Details

#### Point Lifecycle

Points are awarded and revoked automatically based on user activity:

```
Action performed → Points awarded → Totals updated
Action undone    → Points revoked → Totals updated
```

| Event | Awards | Revokes |
|-------|--------|---------|
| New discussion | `discussion_started` | On hide/delete |
| New reply | `post_created` | On hide/delete |
| User login | `daily_login` (once per day) | — |
| Like/unlike | `like_received` + `like_given` | On unlike or post hide/delete |
| React/unreact | `reaction_received` + `reaction_given` | On unreact or post hide/delete |
| Best answer set/unset | `best_answer` | On unset |
| Badge earned | `badge_earned` | — |
| Upvote/downvote | `upvote_received` / `downvote_received` | On vote change |

#### Data Integrity

- **Atomic operations**: Point revocations use database transactions with row locking
- **Cascade handling**: Discussion deletion properly revokes all post-level points (likes, reactions, votes)
- **Content moderation**: Hidden posts/discussions have their points revoked; restored content re-awards points
- **Idempotent awards**: Duplicate point records are prevented with existence checks
- **Daily login protection**: Database fallback prevents double-awarding after cache clear

#### Period Filtering

Rankings are filtered by the `created_at` timestamp of each point record:

| Period | Range |
|--------|-------|
| Daily | Last 24 hours |
| Weekly | Last 7 days |
| Monthly | Last 30 days |
| Quarterly | Last 90 days |
| Yearly | Last 365 days |
| All Time | No filter |

## 🌍 Translations

This extension comes with English translations. Community translations are welcome!

## 💖 Support & Contributing

If you find this extension useful, consider:

- ⭐ Starring the repository on GitHub
- 🐛 Reporting issues on [GitHub](https://github.com/huseyinfiliz/leaderboard/issues)
- 🌐 Contributing translations

## Links

- [Packagist](https://packagist.org/packages/huseyinfiliz/leaderboard)
- [GitHub](https://github.com/huseyinfiliz/leaderboard)

## License

MIT License - see [LICENSE.md](LICENSE.md)

---

Developed with ❤️ by [Hüseyin Filiz](https://github.com/huseyinfiliz)
