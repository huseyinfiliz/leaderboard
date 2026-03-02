/// <reference types="flarum/@types/translator-icu-rich" />
import Modal from 'flarum/common/components/Modal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
interface RecalculateModalAttrs extends IInternalModalAttrs {
    onsuccess: () => void;
}
export default class RecalculateModal<CustomAttrs extends RecalculateModalAttrs = RecalculateModalAttrs> extends Modal<CustomAttrs> {
    private running;
    private done;
    private failed;
    private step;
    private totalSteps;
    private stepKey;
    className(): string;
    title(): import("@askvortsov/rich-icu-message-formatter").NestedStringArray;
    isDismissible(): boolean;
    content(): JSX.Element;
    confirmView(): Mithril.Children;
    progressView(): Mithril.Children;
    successView(): Mithril.Children;
    failedView(): Mithril.Children;
    startRecalculation(): Promise<void>;
}
export {};
