import { useEffect, useState } from 'react';

/**
 * Returns `value` after `delay` ms of stability. Matches the web
 * frontend's `useDebounce` so any logic that depends on the debounced
 * input behaves identically. Cancels the pending timer on every input
 * change so only the final value wins.
 */
export function useDebounce<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const handle = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(handle);
  }, [value, delay]);

  return debounced;
}
