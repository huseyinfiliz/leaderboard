import app from 'flarum/admin/app';
import LeaderboardSettingsPage from './components/LeaderboardSettingsPage';

export { default as extend } from '../common/extend';

app.initializers.add('huseyinfiliz/leaderboard', () => {
  app.extensionData.for('huseyinfiliz-leaderboard').registerPage(LeaderboardSettingsPage);
});
