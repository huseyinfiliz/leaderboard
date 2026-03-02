import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';

export default class LeaderboardEntry extends Model {
  points = Model.attribute<number>('points');
  rank = Model.attribute<number>('rank');
  user = Model.hasOne<User>('user');
}
