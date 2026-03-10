import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import type { IPageAttrs } from 'flarum/common/components/Page';
import IndexPage from 'flarum/forum/components/IndexPage';
import listItems from 'flarum/common/helpers/listItems';
import type Mithril from 'mithril';

import PodiumSection from './PodiumSection';
import TopContendersGrid from './TopContendersGrid';
import LeaderboardList from './LeaderboardList';
import LeaderboardState from '../utils/LeaderboardState';

const PERIODS = ['all', 'yearly', 'quarterly', 'monthly', 'weekly', 'daily'] as const;

export default class LeaderboardPage extends Page<IPageAttrs, LeaderboardState> {
  oninit(vnode: Mithril.Vnode<IPageAttrs, this>) {
    super.oninit(vnode);

    this.state = new LeaderboardState();
    this.state.load('all');
  }

  view() {
    const leaderboardName = app.forum.attribute('huseyinfiliz-leaderboard.leaderboard_name') || 'Leaderboard';

    return (
      <div className="IndexPage LeaderboardPage">
        <header className="Hero LeaderboardHero">
          <div className="container">
            <div className="containerNarrow">
              <h1 className="Hero-title">
                <i aria-hidden="true" className="icon fas fa-trophy" /> {leaderboardName}
              </h1>
            </div>
          </div>
        </header>

        <div className="container">
          <div className="sideNavContainer">
            <nav className="IndexPage-nav sideNav">
              <ul>{listItems(IndexSidebar.prototype.items().toArray())}</ul>
            </nav>
            <div className="IndexPage-results sideNavOffset">
              <div className="LeaderboardPage-filters">
                <button
                  className="Button LeaderboardPage-refreshBtn"
                  onclick={() => this.state.load(this.state.period)}
                  disabled={!this.state.isFullyLoaded}
                >
                  <i className="fas fa-sync-alt" />
                </button>
                {PERIODS.map((p) => (
                  <button
                    key={p}
                    className={'Button LeaderboardPage-filterBtn' + (this.state.period === p ? ' active' : '')}
                    onclick={() => this.state.load(p)}
                    disabled={!this.state.isFullyLoaded}
                  >
                    {app.translator.trans(`huseyinfiliz-leaderboard.forum.period.${p}`)}
                  </button>
                ))}
              </div>

              {this.state.isEmpty ? (
                <div className="LeaderboardPage-empty">
                  <p>{app.translator.trans('huseyinfiliz-leaderboard.forum.list.no_results')}</p>
                </div>
              ) : (
                <div className="LeaderboardPage-content">
                  {this.podiumView()}
                  {this.contendersView()}
                  {this.honorableView()}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    );
  }

  podiumView() {
    if (this.state.podiumLoading) {
      return (
        <div className="LeaderboardPodium LeaderboardSkeleton-podium">
          {[1, 2, 3].map((i) => (
            <div className={`LeaderboardPodium-place LeaderboardPodium-place--${i} LeaderboardSkeleton-card`} key={i}>
              <div className="LeaderboardSkeleton-badge LeaderboardSkeleton-pulse" />
              <div className="LeaderboardSkeleton-avatar LeaderboardSkeleton-pulse" />
              <div className="LeaderboardSkeleton-text LeaderboardSkeleton-pulse" />
              <div className="LeaderboardSkeleton-textSm LeaderboardSkeleton-pulse" />
            </div>
          ))}
        </div>
      );
    }

    if (this.state.podiumEntries.length === 0) return null;
    return <PodiumSection entries={this.state.podiumEntries} />;
  }

  contendersView() {
    if (this.state.contendersLoading) {
      return (
        <div className="LeaderboardContenders LeaderboardSkeleton-contenders">
          <div className="LeaderboardSkeleton-sectionTitle LeaderboardSkeleton-pulse" />
          <div className="LeaderboardContenders-grid">
            {[1, 2, 3, 4, 5, 6, 7].map((i) => (
              <div className="LeaderboardContenders-card LeaderboardSkeleton-card" key={i}>
                <div className="LeaderboardSkeleton-avatar LeaderboardSkeleton-avatarSm LeaderboardSkeleton-pulse" />
                <div className="LeaderboardSkeleton-text LeaderboardSkeleton-pulse" />
                <div className="LeaderboardSkeleton-textSm LeaderboardSkeleton-pulse" />
              </div>
            ))}
          </div>
        </div>
      );
    }

    if (this.state.contenderEntries.length === 0) return null;
    return <TopContendersGrid entries={this.state.contenderEntries} />;
  }

  honorableView() {
    if (this.state.honorableLoading) {
      return (
        <div className="LeaderboardHonorable LeaderboardSkeleton-honorable">
          <div className="LeaderboardSkeleton-sectionTitle LeaderboardSkeleton-pulse" />
          <div className="LeaderboardHonorable-grid">
            {[1, 2, 3, 4].map((i) => (
              <div className="LeaderboardItem LeaderboardSkeleton-item" key={i}>
                <div className="LeaderboardSkeleton-rank LeaderboardSkeleton-pulse" />
                <div className="LeaderboardSkeleton-avatar LeaderboardSkeleton-avatarXs LeaderboardSkeleton-pulse" />
                <div className="LeaderboardSkeleton-text LeaderboardSkeleton-pulse" style={{ flex: 1 }} />
                <div className="LeaderboardSkeleton-textSm LeaderboardSkeleton-pulse" />
              </div>
            ))}
          </div>
        </div>
      );
    }

    if (this.state.honorableEntries.length === 0) return null;
    return <LeaderboardList entries={this.state.honorableEntries} state={this.state} />;
  }
}
