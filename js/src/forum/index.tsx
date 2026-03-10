import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import LinkButton from 'flarum/common/components/LinkButton';
import UserCard from 'flarum/forum/components/UserCard';

import LeaderboardPage from './components/LeaderboardPage';

export { default as extend } from '../common/extend';

app.initializers.add('huseyinfiliz/leaderboard', () => {
  app.routes['huseyinfiliz-leaderboard.index'] = {
    path: '/leaderboard',
    component: LeaderboardPage,
  };

  // Add sidebar nav link
  extend(IndexSidebar.prototype, 'navItems', function (items) {
    const leaderboardName = app.forum.attribute('huseyinfiliz-leaderboard.leaderboard_name') || 'Leaderboard';

    items.add(
      'huseyinfiliz-leaderboard',
      <LinkButton href={app.route('huseyinfiliz-leaderboard.index')} icon="fas fa-trophy">
        {leaderboardName}
      </LinkButton>,
      10
    );
  });

  // Show leaderboard points on user card
  extend(UserCard.prototype, 'infoItems', function (items) {
    const user = (this.attrs as any).user;
    if (!user) return;

    const points = user.attribute('leaderboardPoints');
    if (points === undefined || points === null) return;

    const pointsLabel = app.forum.attribute('huseyinfiliz-leaderboard.points_label') || 'Points';

    items.add(
      'huseyinfiliz-leaderboard-points',
      <span className="UserCard-leaderboardPoints">
        <i className="fas fa-trophy" /> {points} {pointsLabel}
      </span>,
      -10
    );
  });
});
