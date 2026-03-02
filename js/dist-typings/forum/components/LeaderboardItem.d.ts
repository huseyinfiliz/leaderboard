/// <reference types="mithril" />
import Component from 'flarum/common/Component';
import type LeaderboardEntry from '../../common/models/LeaderboardEntry';
interface ItemAttrs {
    entry: LeaderboardEntry;
    pointsLabel: string;
}
export default class LeaderboardItem extends Component<ItemAttrs> {
    view(): JSX.Element;
}
export {};
