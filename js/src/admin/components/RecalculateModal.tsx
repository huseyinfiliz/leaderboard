import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';

const SYNC_STEP_KEYS = [
  'discussion_started',
  'post_created',
  'like_received',
  'reaction_received',
  'best_answer',
  'vote_received',
  'badge_earned',
  'rebuild_totals',
];

interface RecalculateModalAttrs extends IInternalModalAttrs {
  onsuccess: () => void;
}

export default class RecalculateModal<CustomAttrs extends RecalculateModalAttrs = RecalculateModalAttrs> extends Modal<CustomAttrs> {
  private running: boolean = false;
  private done: boolean = false;
  private failed: boolean = false;
  private step: number = 0;
  private totalSteps: number = SYNC_STEP_KEYS.length;
  private stepKey: string = SYNC_STEP_KEYS[0];

  className() {
    return 'RecalculateModal Modal--large';
  }

  title() {
    return app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_points_title');
  }

  isDismissible(): boolean {
    return !this.running;
  }

  content() {
    return (
      <div className="Modal-body">
        {!this.running && !this.done && !this.failed && this.confirmView()}
        {this.running && this.progressView()}
        {this.done && this.successView()}
        {this.failed && this.failedView()}
      </div>
    );
  }

  confirmView(): Mithril.Children {
    return (
      <div className="RecalculateModal-confirm">
        <div className="RecalculateModal-warning">
          <i className="fas fa-exclamation-triangle" />
          <span>{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_points_warning')}</span>
        </div>
        <p className="helpText">{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_points_confirm')}</p>
        <div className="RecalculateModal-actions">
          <Button className="Button Button--danger" onclick={() => this.startRecalculation()}>
            {app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_points_button')}
          </Button>
          <Button className="Button" onclick={() => this.hide()}>
            {app.translator.trans('huseyinfiliz-leaderboard.admin.modals.cancel')}
          </Button>
        </div>
      </div>
    );
  }

  progressView(): Mithril.Children {
    const progress = (this.step / this.totalSteps) * 100;

    return (
      <div className="RecalculateModal-progress">
        <div className="RecalculateModal-warning">
          <i className="fas fa-exclamation-triangle" />
          <span>{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_points_warning')}</span>
        </div>
        <div className="LeaderboardSettings-progress">
          <div className="LeaderboardSettings-progressBar">
            <div className="LeaderboardSettings-progressFill" style={{ width: progress + '%' }} />
          </div>
          <span className="LeaderboardSettings-progressText">
            {app.translator.trans(`huseyinfiliz-leaderboard.admin.settings.sync_step_${this.stepKey}`)} ({this.step}/{this.totalSteps})
          </span>
        </div>
      </div>
    );
  }

  successView(): Mithril.Children {
    return (
      <div className="RecalculateModal-success">
        <div className="RecalculateModal-successIcon">
          <i className="fas fa-check-circle" />
        </div>
        <p>{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_points_success')}</p>
        <Button className="Button Button--primary" onclick={() => this.hide()}>
          {app.translator.trans('huseyinfiliz-leaderboard.admin.modals.close')}
        </Button>
      </div>
    );
  }

  failedView(): Mithril.Children {
    return (
      <div className="RecalculateModal-failed">
        <div className="RecalculateModal-failedIcon">
          <i className="fas fa-times-circle" />
        </div>
        <p>{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_points_failed')}</p>
        <div className="RecalculateModal-actions">
          <Button className="Button Button--danger" onclick={() => this.startRecalculation()}>
            {app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_points_retry')}
          </Button>
          <Button className="Button" onclick={() => this.hide()}>
            {app.translator.trans('huseyinfiliz-leaderboard.admin.modals.close')}
          </Button>
        </div>
      </div>
    );
  }

  async startRecalculation() {
    this.running = true;
    this.done = false;
    this.failed = false;
    this.step = 0;
    this.stepKey = SYNC_STEP_KEYS[0];
    m.redraw();

    try {
      let isDone = false;
      let currentStep = 0;

      while (!isDone) {
        this.stepKey = SYNC_STEP_KEYS[currentStep] || 'rebuild_totals';
        this.step = currentStep;
        m.redraw();

        const response = (await app.request({
          method: 'POST',
          url: app.forum.attribute('apiUrl') + '/leaderboard-entries/recalculate',
          body: { action: 'sync-events', step: currentStep },
          errorHandler: (error: any) => {
            if (error.status === 409) {
              app.alerts.show({ type: 'error' }, app.translator.trans('huseyinfiliz-leaderboard.admin.settings.already_running'));
            }
            throw error;
          },
        })) as any;

        if (typeof response.step !== 'number') break;

        this.totalSteps = response.totalSteps;
        isDone = response.done;
        currentStep = response.step + 1;
      }

      this.step = this.totalSteps;
      this.running = false;
      this.done = true;
      this.attrs.onsuccess();
      m.redraw();
    } catch (e) {
      this.running = false;
      this.failed = true;
      m.redraw();
    }
  }
}
