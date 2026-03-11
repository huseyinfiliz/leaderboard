import Form from 'flarum/common/components/Form';
import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import GroupBadge from 'flarum/common/components/GroupBadge';
import type Group from 'flarum/common/models/Group';
import type Mithril from 'mithril';

import SelectGroupsModal from './SelectGroupsModal';
import RecalculateModal from './RecalculateModal';

const POINT_REASONS = [
  'discussion_started',
  'post_created',
  'daily_login',
  'like_received',
  'like_given',
  'reaction_received',
  'reaction_given',
  'best_answer',
  'badge_earned',
  'upvote_received',
  'downvote_received',
];

export default class LeaderboardSettingsPage extends ExtensionPage {
  private rebuilding: boolean = false;
  private activeTab: string = 'general';
  private expandedSections: Set<string> = new Set(['core']);
  private selectedGroupIds: number[] = [];
  private selectedTagIds: number[] = [];
  private pointsChanged: boolean = false;
  private tagsChanged: boolean = false;
  private initialPointValues: Record<string, string> = {};
  private initialTagIds: string = '[]';

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.initGroupSelection();
    this.initTagSelection();
    this.captureInitialPointValues();
    this.initialTagIds = this.setting('huseyinfiliz-leaderboard.excluded_tags')() || '[]';
  }

  captureInitialPointValues() {
    for (const reason of POINT_REASONS) {
      this.initialPointValues[reason] = this.setting(`huseyinfiliz-leaderboard.points_${reason}`)();
    }
  }

  onsaved() {
    super.onsaved();

    for (const reason of POINT_REASONS) {
      const current = this.setting(`huseyinfiliz-leaderboard.points_${reason}`)();
      if (current !== this.initialPointValues[reason]) {
        this.pointsChanged = true;
        break;
      }
    }

    const currentTagIds = this.setting('huseyinfiliz-leaderboard.excluded_tags')() || '[]';
    if (currentTagIds !== this.initialTagIds) {
      this.tagsChanged = true;
    }

    this.captureInitialPointValues();
    this.initialTagIds = currentTagIds;
  }

  initGroupSelection() {
    const value = this.setting('huseyinfiliz-leaderboard.excluded_groups')();
    let ids: number[] = [];
    try {
      ids = JSON.parse(value || '[]');
    } catch (e) {
      // ignore
    }
    this.selectedGroupIds = Array.isArray(ids) ? ids : [];
  }

  initTagSelection() {
    const value = this.setting('huseyinfiliz-leaderboard.excluded_tags')();
    let ids: number[] = [];
    try {
      ids = JSON.parse(value || '[]');
    } catch (e) {
      // ignore
    }
    this.selectedTagIds = Array.isArray(ids) ? ids : [];
  }

  isExtensionEnabled(id: string): boolean {
    const enabledExtensions: string[] = JSON.parse((app.data.settings.extensions_enabled as string) || '[]');
    return enabledExtensions.includes(id);
  }

  content() {
    return (
      <div className="LeaderboardSettings">
        <div className="LeaderboardSettings-header">
          <div className="LeaderboardSettings-tabs">
            {this.tabButton('general', 'fas fa-cog', 'huseyinfiliz-leaderboard.admin.tabs.general')}
            {this.tabButton('points', 'fas fa-star', 'huseyinfiliz-leaderboard.admin.tabs.points')}
            {this.tabButton('exclusions', 'fas fa-ban', 'huseyinfiliz-leaderboard.admin.tabs.exclusions')}
            {this.tabButton('maintenance', 'fas fa-wrench', 'huseyinfiliz-leaderboard.admin.tabs.maintenance')}
          </div>
        </div>

        {this.pointsChanged && (
          <div className="LeaderboardSettings-syncAlert">
            <i className="fas fa-exclamation-triangle" />
            <span>{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.points_changed_notice')}</span>
            <Button className="Button Button--primary Button--small" loading={this.rebuilding} onclick={() => this.rebuildTotals()}>
              {app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_totals_button')}
            </Button>
          </div>
        )}

        {this.tagsChanged && (
          <div className="LeaderboardSettings-syncAlert">
            <i className="fas fa-exclamation-triangle" />
            <span>{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.tags_changed_notice')}</span>
            <Button className="Button Button--primary Button--small" onclick={() => this.openRecalculateModal()}>
              {app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_points_button')}
            </Button>
          </div>
        )}

        <div className="LeaderboardSettings-content">
          {this.activeTab === 'general' && this.generalTab()}
          {this.activeTab === 'points' && this.pointsTab()}
          {this.activeTab === 'exclusions' && this.exclusionsTab()}
          {this.activeTab === 'maintenance' && this.maintenanceTab()}
        </div>
      </div>
    );
  }

  tabButton(tab: string, iconClass: string, labelKey: string): Mithril.Children {
    return (
      <Button
        className={'Button ' + (this.activeTab === tab ? 'Button--primary' : '')}
        icon={iconClass}
        onclick={() => {
          this.activeTab = tab;
        }}
      >
        {app.translator.trans(labelKey)}
      </Button>
    );
  }

  generalTab(): Mithril.Children {
    return (
      <Form>
        <div className="Form-group">
          <label>{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.leaderboard_name_label')}</label>
          <input className="FormControl" bidi={this.setting('huseyinfiliz-leaderboard.leaderboard_name')} placeholder="Leaderboard" />
        </div>
        <div className="Form-group">
          <label>{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.points_label_label')}</label>
          <input className="FormControl" bidi={this.setting('huseyinfiliz-leaderboard.points_label')} placeholder="Points" />
        </div>
        <div className="Form-group">{this.submitButton()}</div>
      </Form>
    );
  }

  toggleSection(key: string) {
    if (this.expandedSections.has(key)) {
      this.expandedSections.delete(key);
    } else {
      this.expandedSections.add(key);
    }
  }

  pointsTab(): Mithril.Children {
    return (
      <Form>
        {this.buildAccordionSection('core', 'fas fa-cube', app.translator.trans('huseyinfiliz-leaderboard.admin.settings.section_core'), null, [
          this.buildPointInput('discussion_started'),
          this.buildPointInput('post_created'),
          this.buildPointInput('daily_login'),
        ])}
        {this.buildAccordionSection(
          'likes',
          'fas fa-heart',
          app.translator.trans('huseyinfiliz-leaderboard.admin.settings.section_likes'),
          'flarum-likes',
          [
            this.buildPointInput('like_received', !this.isExtensionEnabled('flarum-likes')),
            this.buildPointInput('like_given', !this.isExtensionEnabled('flarum-likes')),
          ]
        )}
        {this.buildAccordionSection(
          'reactions',
          'fas fa-smile',
          app.translator.trans('huseyinfiliz-leaderboard.admin.settings.section_reactions'),
          'fof-reactions',
          [
            this.buildPointInput('reaction_received', !this.isExtensionEnabled('fof-reactions')),
            this.buildPointInput('reaction_given', !this.isExtensionEnabled('fof-reactions')),
          ]
        )}
        {this.buildAccordionSection(
          'best_answer',
          'fas fa-check-circle',
          app.translator.trans('huseyinfiliz-leaderboard.admin.settings.section_best_answer'),
          'fof-best-answer',
          [this.buildPointInput('best_answer', !this.isExtensionEnabled('fof-best-answer'))]
        )}
        {this.buildAccordionSection(
          'badges',
          'fas fa-certificate',
          app.translator.trans('huseyinfiliz-leaderboard.admin.settings.section_badges'),
          'fof-badges',
          [this.buildPointInput('badge_earned', !this.isExtensionEnabled('fof-badges'))]
        )}
        {this.buildAccordionSection(
          'gamification',
          'fas fa-arrow-up',
          app.translator.trans('huseyinfiliz-leaderboard.admin.settings.section_gamification'),
          'fof-gamification',
          [
            this.buildPointInput('upvote_received', !this.isExtensionEnabled('fof-gamification')),
            this.buildPointInput('downvote_received', !this.isExtensionEnabled('fof-gamification')),
          ]
        )}
        <div className="Form-group">{this.submitButton()}</div>
      </Form>
    );
  }

  buildAccordionSection(
    key: string,
    icon: string,
    title: string | Mithril.Children,
    extensionId: string | null,
    content: Mithril.Children[]
  ): Mithril.Children {
    const isExpanded = this.expandedSections.has(key);
    const isEnabled = extensionId === null || this.isExtensionEnabled(extensionId);

    return (
      <div
        className={
          'LeaderboardSettings-accordion' +
          (isExpanded ? ' LeaderboardSettings-accordion--open' : '') +
          (!isEnabled ? ' LeaderboardSettings-accordion--disabled' : '')
        }
      >
        <button className="LeaderboardSettings-accordionHeader" type="button" onclick={() => this.toggleSection(key)}>
          <i className={icon + ' LeaderboardSettings-accordionIcon'} />
          <span className="LeaderboardSettings-accordionTitle">{title}</span>
          {!isEnabled && (
            <span className="LeaderboardSettings-accordionBadge">
              {app.translator.trans('huseyinfiliz-leaderboard.admin.settings.extension_not_enabled')}
            </span>
          )}
          <i className={'fas ' + (isExpanded ? 'fa-chevron-up' : 'fa-chevron-down') + ' LeaderboardSettings-accordionChevron'} />
        </button>
        {isExpanded && <div className="LeaderboardSettings-accordionBody">{content}</div>}
      </div>
    );
  }

  buildPointInput(reason: string, disabled: boolean = false): Mithril.Children {
    const key = `huseyinfiliz-leaderboard.points_${reason}`;
    const labelKey = `huseyinfiliz-leaderboard.admin.settings.points_${reason}_label`;

    return (
      <div className="Form-group">
        <label>{app.translator.trans(labelKey)}</label>
        <input className="FormControl" type="number" bidi={this.setting(key)} disabled={disabled} />
      </div>
    );
  }

  exclusionsTab(): Mithril.Children {
    const groups = app.store.all<Group>('groups');
    const selectedGroups = groups.filter((g) => this.selectedGroupIds.includes(Number(g.id())));

    return (
      <Form>
        <div className="Form-group">
          <label>{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.excluded_groups_label')}</label>
          <p className="helpText">{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.excluded_groups_help')}</p>
          <div className="LeaderboardSettings-selectedItems">
            {selectedGroups.length > 0 ? (
              selectedGroups.map((group) => (
                <span className="LeaderboardSettings-badge" key={group.id()}>
                  <GroupBadge group={group} label={null} /> {group.nameSingular()}
                </span>
              ))
            ) : (
              <span className="LeaderboardSettings-none">{app.translator.trans('huseyinfiliz-leaderboard.admin.modals.none_selected')}</span>
            )}
          </div>
          <Button
            className="Button"
            icon="fas fa-users"
            onclick={() => {
              app.modal.show(SelectGroupsModal, {
                selectedGroupIds: this.selectedGroupIds,

                onsubmit: (ids: number[]) => {
                  this.selectedGroupIds = ids;
                  this.setting('huseyinfiliz-leaderboard.excluded_groups')(JSON.stringify(ids));
                },
              });
            }}
          >
            {app.translator.trans('huseyinfiliz-leaderboard.admin.modals.select_groups')}
          </Button>
        </div>
        {this.isExtensionEnabled('flarum-tags') && this.tagsExclusionSection()}
        <div className="Form-group">{this.submitButton()}</div>
      </Form>
    );
  }

  tagsExclusionSection(): Mithril.Children {
    const tagLabel = require('ext:flarum/tags/common/helpers/tagLabel');
    const sortTags = require('ext:flarum/tags/common/utils/sortTags');

    const allTags = sortTags(app.store.all('tags'));
    const selectedTags = allTags.filter((t: any) => this.selectedTagIds.includes(Number(t.id())));

    return (
      <div className="Form-group">
        <label>{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.excluded_tags_label')}</label>
        <p className="helpText">{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.excluded_tags_help')}</p>
        <div className="LeaderboardSettings-selectedItems">
          {selectedTags.length > 0 ? (
            selectedTags.map((tag: any) => <span key={tag.id()}>{tagLabel(tag)}</span>)
          ) : (
            <span className="LeaderboardSettings-none">{app.translator.trans('huseyinfiliz-leaderboard.admin.modals.none_selected')}</span>
          )}
        </div>
        <Button
          className="Button"
          icon="fas fa-tags"
          onclick={() => {
            const currentSelected = allTags.filter((t: any) => this.selectedTagIds.includes(Number(t.id())));

            app.modal.show(
              () => import('ext:flarum/tags/common/components/TagSelectionModal'),
              {
                selectedTags: currentSelected,
                onsubmit: (tags: any[]) => {
                  this.selectedTagIds = tags.map((t: any) => Number(t.id()));
                  this.setting('huseyinfiliz-leaderboard.excluded_tags')(JSON.stringify(this.selectedTagIds));
                  m.redraw();
                },
              }
            );
          }}
        >
          {app.translator.trans('huseyinfiliz-leaderboard.admin.modals.select_tags')}
        </Button>
      </div>
    );
  }

  maintenanceTab(): Mithril.Children {
    return (
      <Form>
        <div className="Form-group">
          <h3>{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_points_title')}</h3>
          <p className="helpText">{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_points_help')}</p>
          <Button className="Button Button--danger" onclick={() => this.openRecalculateModal()}>
            {app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_points_button')}
          </Button>
        </div>
        <div className="Form-group">
          <h3>{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_totals_title')}</h3>
          <p className="helpText">{app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_totals_help')}</p>
          <Button className="Button" loading={this.rebuilding} onclick={() => this.rebuildTotals()}>
            {app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_totals_button')}
          </Button>
        </div>
      </Form>
    );
  }

  openRecalculateModal() {
    app.modal.show(RecalculateModal, {
      onsuccess: () => {
        this.pointsChanged = false;
        this.tagsChanged = false;
        m.redraw();
      },
    });
  }

  async rebuildTotals() {
    if (this.rebuilding) return;

    this.rebuilding = true;
    m.redraw();

    try {
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/leaderboard-entries/recalculate',
        body: { action: 'rebuild-totals' },
      });

      this.pointsChanged = false;
      app.alerts.show({ type: 'success' }, app.translator.trans('huseyinfiliz-leaderboard.admin.settings.recalculate_totals_success'));
    } catch (e) {
      // Error handled by Flarum
    } finally {
      this.rebuilding = false;
      m.redraw();
    }
  }
}
