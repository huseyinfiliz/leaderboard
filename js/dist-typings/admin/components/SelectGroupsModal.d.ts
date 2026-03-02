/// <reference types="flarum/@types/translator-icu-rich" />
import Modal from 'flarum/common/components/Modal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Group from 'flarum/common/models/Group';
import type Mithril from 'mithril';
interface SelectGroupsModalAttrs extends IInternalModalAttrs {
    selectedGroupIds: number[];
    onsubmit: (ids: number[]) => void;
}
export default class SelectGroupsModal<CustomAttrs extends SelectGroupsModalAttrs = SelectGroupsModalAttrs> extends Modal<CustomAttrs> {
    private selected;
    oninit(vnode: Mithril.Vnode<CustomAttrs>): void;
    className(): string;
    title(): import("@askvortsov/rich-icu-message-formatter").NestedStringArray;
    content(): JSX.Element;
    getAvailableGroups(): Group[];
    submit(): void;
}
export {};
