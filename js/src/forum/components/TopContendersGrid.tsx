import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import type Mithril from 'mithril';

import type LeaderboardEntry from '../../common/models/LeaderboardEntry';

interface ContendersAttrs {
  entries: LeaderboardEntry[];
}

export default class TopContendersGrid extends Component<ContendersAttrs> {
  view() {
    const entries = this.attrs.entries;

    if (entries.length === 0) {
      return null;
    }

    const pointsLabel = app.forum.attribute('huseyinfiliz-leaderboard.points_label') || 'Points';

    return (
      <div className="LeaderboardContenders">
        <h3 className="LeaderboardContenders-title">
          <i className="fas fa-medal" />
          {app.translator.trans('huseyinfiliz-leaderboard.forum.contenders.title')}
        </h3>
        <div className="LeaderboardContenders-grid">
          {entries.map((entry) => {
            const user = entry.user();
            const rank = entry.rank();

            return (
              <Link href={user ? app.route('user', { username: user.slug() }) : '#'} className="LeaderboardContenders-card" key={entry.id()}>
                <span className="LeaderboardContenders-rank">#{rank}</span>
                <div className="LeaderboardContenders-avatar">{user ? <Avatar user={user} /> : <span className="Avatar">?</span>}</div>
                <span className="LeaderboardContenders-name">{user ? user.displayName() : '?'}</span>
                <span className="LeaderboardContenders-points">
                  {entry.points()} {pointsLabel}
                </span>
              </Link>
            );
          })}
        </div>
      </div>
    );
  }
}
