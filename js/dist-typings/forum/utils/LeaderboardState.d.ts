import type LeaderboardEntry from '../../common/models/LeaderboardEntry';
export default class LeaderboardState {
    podiumEntries: LeaderboardEntry[];
    contenderEntries: LeaderboardEntry[];
    honorableEntries: LeaderboardEntry[];
    podiumLoading: boolean;
    contendersLoading: boolean;
    honorableLoading: boolean;
    loadingMore: boolean;
    period: string;
    honorableOffset: number;
    hasMore: boolean;
    load(period: string): Promise<void>;
    loadMore(): Promise<void>;
    private fetchPodium;
    private fetchContenders;
    private fetchHonorable;
    get isFullyLoaded(): boolean;
    get isEmpty(): boolean;
}
