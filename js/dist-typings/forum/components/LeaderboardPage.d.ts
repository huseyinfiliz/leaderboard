import Page from 'flarum/common/components/Page';
import type { IPageAttrs } from 'flarum/common/components/Page';
import type Mithril from 'mithril';
import LeaderboardState from '../utils/LeaderboardState';
export default class LeaderboardPage extends Page<IPageAttrs, LeaderboardState> {
    oninit(vnode: Mithril.Vnode<IPageAttrs, this>): void;
    view(): JSX.Element;
    podiumView(): JSX.Element | null;
    contendersView(): JSX.Element | null;
    honorableView(): JSX.Element | null;
}
