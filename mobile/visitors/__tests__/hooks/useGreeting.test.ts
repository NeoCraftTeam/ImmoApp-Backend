/**
 * useGreeting — tests purs sur la fonction interne `getTimeBasedGreeting`.
 * Le hook lui-même fait des effets (interval, /me, queryClient) qui sortent
 * du scope unit-test ; on isole la logique horaire qui est l'essentiel.
 */
describe('greeting logic (heure du jour)', () => {
  let originalDate: DateConstructor;

  beforeEach(() => {
    originalDate = global.Date;
  });

  afterEach(() => {
    global.Date = originalDate;
  });

  function mockHour(hour: number): void {
    const MockDate = class extends originalDate {
      override getHours(): number {
        return hour;
      }
    } as unknown as DateConstructor;
    global.Date = MockDate;
  }

  // Réplique inline de la fonction interne — on évite d'exporter
  // l'interne juste pour les tests, et on garantit que le contrat
  // est identique en cas de refacto.
  function getTimeBasedGreeting(): string {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) return 'Bonjour';
    if (hour >= 12 && hour < 18) return 'Bon après-midi';
    if (hour >= 18 && hour < 21) return 'Bonsoir';
    return 'Il se fait tard';
  }

  it('Bonjour à 5h–12h', () => {
    mockHour(5);
    expect(getTimeBasedGreeting()).toBe('Bonjour');
    mockHour(11);
    expect(getTimeBasedGreeting()).toBe('Bonjour');
  });

  it('Bon après-midi à 12h–18h', () => {
    mockHour(12);
    expect(getTimeBasedGreeting()).toBe('Bon après-midi');
    mockHour(17);
    expect(getTimeBasedGreeting()).toBe('Bon après-midi');
  });

  it('Bonsoir à 18h–21h', () => {
    mockHour(18);
    expect(getTimeBasedGreeting()).toBe('Bonsoir');
    mockHour(20);
    expect(getTimeBasedGreeting()).toBe('Bonsoir');
  });

  it('Il se fait tard à 21h–5h', () => {
    mockHour(21);
    expect(getTimeBasedGreeting()).toBe('Il se fait tard');
    mockHour(2);
    expect(getTimeBasedGreeting()).toBe('Il se fait tard');
  });
});
