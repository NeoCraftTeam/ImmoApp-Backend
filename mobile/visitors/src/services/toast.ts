/**
 * Toasts applicatifs — émetteur module léger (sans dépendance), consommé
 * par `<ToastHost>` monté à la racine. Appelable depuis n'importe où
 * (hooks, mutations, écrans) via `showToast(...)`, sans threading de
 * contexte.
 */

export type ToastType = 'success' | 'error' | 'info';

export interface ToastPayload {
  id: number;
  message: string;
  type: ToastType;
  /** Action optionnelle (ex. « Voir ») — libellé + callback. */
  actionLabel?: string;
  onAction?: () => void;
  durationMs: number;
}

type Listener = (toast: ToastPayload) => void;

let listener: Listener | null = null;
let nextId = 1;

export function subscribeToast(cb: Listener): () => void {
  listener = cb;
  return () => {
    if (listener === cb) listener = null;
  };
}

export function showToast(input: {
  message: string;
  type?: ToastType;
  actionLabel?: string;
  onAction?: () => void;
  durationMs?: number;
}): void {
  listener?.({
    id: nextId++,
    message: input.message,
    type: input.type ?? 'info',
    actionLabel: input.actionLabel,
    onAction: input.onAction,
    durationMs: input.durationMs ?? 3500,
  });
}
