import app from 'flarum/forum/app';
import type LeaderboardEntry from '../../common/models/LeaderboardEntry';

export default class LeaderboardState {
  podiumEntries: LeaderboardEntry[] = [];
  contenderEntries: LeaderboardEntry[] = [];
  honorableEntries: LeaderboardEntry[] = [];

  podiumLoading: boolean = false;
  contendersLoading: boolean = false;
  honorableLoading: boolean = false;
  loadingMore: boolean = false;

  period: string = 'all';
  honorableOffset: number = 0;
  hasMore: boolean = false;

  async load(period: string) {
    this.period = period;
    this.honorableOffset = 0;
    this.podiumEntries = [];
    this.contenderEntries = [];
    this.honorableEntries = [];
    this.podiumLoading = true;
    this.contendersLoading = true;
    this.honorableLoading = true;
    this.hasMore = false;
    m.redraw();

    await Promise.all([this.fetchPodium(), this.fetchContenders(), this.fetchHonorable(false)]);
  }

  async loadMore() {
    if (!this.hasMore || this.loadingMore) return;

    this.loadingMore = true;
    m.redraw();

    await this.fetchHonorable(true);
  }

  private async fetchPodium() {
    try {
      const results = await app.store.find<LeaderboardEntry>('leaderboard-entries', {
        include: 'user',
        filter: { period: this.period, section: 'podium' },
      } as any);

      this.podiumEntries = Array.isArray(results) ? results : [results];
    } catch (e) {
      // Error handled by Flarum
    } finally {
      this.podiumLoading = false;
      m.redraw();
    }
  }

  private async fetchContenders() {
    try {
      const results = await app.store.find<LeaderboardEntry>('leaderboard-entries', {
        include: 'user',
        filter: { period: this.period, section: 'contenders' },
      } as any);

      this.contenderEntries = Array.isArray(results) ? results : [results];
    } catch (e) {
      // Error handled by Flarum
    } finally {
      this.contendersLoading = false;
      m.redraw();
    }
  }

  private async fetchHonorable(append: boolean) {
    try {
      const results = await app.store.find<LeaderboardEntry>('leaderboard-entries', {
        include: 'user',
        filter: { period: this.period, section: 'honorable' },
        page: { offset: this.honorableOffset, limit: 20 },
      } as any);

      const payload = (results as any).payload;
      const newEntries: LeaderboardEntry[] = Array.isArray(results) ? results : [results];

      if (append) {
        this.honorableEntries = [...this.honorableEntries, ...newEntries];
      } else {
        this.honorableEntries = newEntries;
      }

      this.honorableOffset += newEntries.length;

      // JSON:API standard: if there's a "next" link, there are more results
      this.hasMore = !!payload?.links?.next;
    } catch (e) {
      // Error handled by Flarum
    } finally {
      this.honorableLoading = false;
      this.loadingMore = false;
      m.redraw();
    }
  }

  get isFullyLoaded(): boolean {
    return !this.podiumLoading && !this.contendersLoading && !this.honorableLoading;
  }

  get isEmpty(): boolean {
    return this.isFullyLoaded && this.podiumEntries.length === 0 && this.contenderEntries.length === 0 && this.honorableEntries.length === 0;
  }
}
