/// <reference types="mithril" />
import Component from 'flarum/common/Component';
import type LeaderboardState from '../utils/LeaderboardState';
import type LeaderboardEntry from '../../common/models/LeaderboardEntry';
interface ListAttrs {
    entries: LeaderboardEntry[];
    state: LeaderboardState;
}
export default class LeaderboardList extends Component<ListAttrs> {
    view(): JSX.Element;
}
export {};
