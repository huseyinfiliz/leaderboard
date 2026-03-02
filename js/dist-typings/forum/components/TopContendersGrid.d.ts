/// <reference types="mithril" />
import Component from 'flarum/common/Component';
import type LeaderboardEntry from '../../common/models/LeaderboardEntry';
interface ContendersAttrs {
    entries: LeaderboardEntry[];
}
export default class TopContendersGrid extends Component<ContendersAttrs> {
    view(): JSX.Element | null;
}
export {};
