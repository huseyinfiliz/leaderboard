import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import avatar from 'flarum/common/helpers/avatar';
import type Mithril from 'mithril';

import type LeaderboardEntry from '../../common/models/LeaderboardEntry';

interface ItemAttrs {
  entry: LeaderboardEntry;
  pointsLabel: string;
}

export default class LeaderboardItem extends Component<ItemAttrs> {
  view() {
    const { entry, pointsLabel } = this.attrs;
    const user = entry.user();
    const rank = entry.rank();

    return (
      <Link href={user ? app.route('user', { username: user.slug() }) : '#'} className="LeaderboardItem">
        <span className="LeaderboardItem-rank">#{rank}</span>
        <span className="LeaderboardItem-avatar">{user ? avatar(user) : <span className="Avatar">?</span>}</span>
        <span className="LeaderboardItem-name">{user ? user.displayName() : '?'}</span>
        <span className="LeaderboardItem-points">
          {entry.points()} {pointsLabel}
        </span>
      </Link>
    );
  }
}
