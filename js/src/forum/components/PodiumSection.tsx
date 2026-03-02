import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import avatar from 'flarum/common/helpers/avatar';
import type Mithril from 'mithril';

import type LeaderboardEntry from '../../common/models/LeaderboardEntry';

interface PodiumAttrs {
  entries: LeaderboardEntry[];
}

export default class PodiumSection extends Component<PodiumAttrs> {
  view() {
    const entries = this.attrs.entries;

    if (entries.length === 0) {
      return null;
    }

    const pointsLabel = app.forum.attribute('huseyinfiliz-leaderboard.points_label') || 'Points';

    // Display order: 2nd, 1st, 3rd (desktop uses CSS order, mobile overrides)
    const ordered = [entries[1], entries[0], entries[2]].filter(Boolean);

    return (
      <div className="LeaderboardPodium">
        {ordered.map((entry) => {
          const rank = entry.rank();
          const user = entry.user();
          const placeClass = `LeaderboardPodium-place--${rank}`;
          const medalClass = rank === 1 ? 'gold' : rank === 2 ? 'silver' : 'bronze';

          return (
            <Link
              href={user ? app.route('user', { username: user.slug() }) : '#'}
              className={`LeaderboardPodium-place ${placeClass}`}
              key={entry.id()}
            >
              <div className={`LeaderboardPodium-medal LeaderboardPodium-medal--${medalClass}`}>
                {rank === 1 && <i className="fas fa-crown LeaderboardPodium-crown" />}
                <span className="LeaderboardPodium-rankNumber">#{rank}</span>
              </div>
              <div className="LeaderboardPodium-avatar">{user ? avatar(user) : <span className="Avatar">?</span>}</div>
              <div className="LeaderboardPodium-name">{user ? user.displayName() : '?'}</div>
              <div className="LeaderboardPodium-points">
                {entry.points()} {pointsLabel}
              </div>
              {user && this.statsView(user)}
            </Link>
          );
        })}
      </div>
    );
  }

  statsView(user: any) {
    const comments = user.attribute('commentCount');
    const discussions = user.attribute('discussionCount');
    const badges = user.attribute('badgeCount');

    return (
      <div className="LeaderboardPodium-stats">
        <span className="LeaderboardPodium-stat" title={app.translator.trans('huseyinfiliz-leaderboard.forum.podium.stat_comments') as string}>
          <i className="fas fa-comment" />
          {comments ?? 0}
        </span>
        <span className="LeaderboardPodium-stat" title={app.translator.trans('huseyinfiliz-leaderboard.forum.podium.stat_discussions') as string}>
          <i className="fas fa-comments" />
          {discussions ?? 0}
        </span>
        {badges !== undefined && badges !== null && (
          <span className="LeaderboardPodium-stat" title={app.translator.trans('huseyinfiliz-leaderboard.forum.podium.stat_badges') as string}>
            <i className="fas fa-certificate" />
            {badges}
          </span>
        )}
      </div>
    );
  }
}
