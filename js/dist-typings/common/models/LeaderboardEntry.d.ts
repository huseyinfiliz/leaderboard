import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
export default class LeaderboardEntry extends Model {
    points: () => number;
    rank: () => number;
    user: () => false | User;
}
