import { vi } from 'vitest';

/**
 * The vitest environment this project runs under exposes Node's own
 * (non-functional without --localstorage-file) `localStorage` global instead
 * of a working jsdom one, so tests that touch AuthService's token storage
 * need to stub it explicitly. Call installFakeLocalStorage() in beforeEach
 * and vi.unstubAllGlobals() in afterEach.
 */
export function installFakeLocalStorage(): Storage {
  const store = new Map<string, string>();

  const fake: Storage = {
    getItem: (key: string) => store.get(key) ?? null,
    setItem: (key: string, value: string) => void store.set(key, value),
    removeItem: (key: string) => void store.delete(key),
    clear: () => store.clear(),
    key: (index: number) => Array.from(store.keys())[index] ?? null,
    get length() {
      return store.size;
    },
  };

  vi.stubGlobal('localStorage', fake);
  return fake;
}
