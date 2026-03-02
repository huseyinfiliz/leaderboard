/// <reference types="mithril" />
import Component from 'flarum/common/Component';
import type LeaderboardEntry from '../../common/models/LeaderboardEntry';
interface PodiumAttrs {
    entries: LeaderboardEntry[];
}
export default class PodiumSection extends Component<PodiumAttrs> {
    view(): JSX.Element | null;
    statsView(user: any): JSX.Element;
}
export {};
