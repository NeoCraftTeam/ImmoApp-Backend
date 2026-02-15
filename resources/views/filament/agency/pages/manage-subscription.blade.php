<x-filament-panels::page>
    <style>
        /* ===== DESIGN TOKENS ===== */
        :root {
            --sub-primary: #2563eb;
            --sub-primary-light: #3b82f6;
            --sub-accent: #7c3aed;
            --sub-success: #10b981;
            --sub-warning: #f59e0b;
            --sub-danger: #ef4444;
            --sub-surface: #ffffff;
            --sub-surface-alt: #f8fafc;
            --sub-border: #e2e8f0;
            --sub-text: #0f172a;
            --sub-text-muted: #64748b;
            --sub-radius: 16px;
            --sub-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            --sub-shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .dark {
            --sub-surface: #1e293b;
            --sub-surface-alt: #0f172a;
            --sub-border: #334155;
            --sub-text: #f1f5f9;
            --sub-text-muted: #94a3b8;
            --sub-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            --sub-shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.4);
        }

        /* ===== BASE ===== */
        .sub-page {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* ===== ACTIVE SUB BANNER ===== */
        .sub-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            border-radius: var(--sub-radius);
            padding: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: 2.5rem;
            box-shadow: 0 20px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .sub-banner::before {
            content: '';
            position: absolute;
            top: -80px;
            right: -80px;
            width: 250px;
            height: 250px;
            background: var(--sub-primary);
            opacity: 0.08;
            border-radius: 50%;
            filter: blur(60px);
        }

        .sub-banner-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
            position: relative;
            z-index: 1;
        }

        .sub-banner-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(37, 99, 235, 0.2);
            color: #60a5fa;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border: 1px solid rgba(37, 99, 235, 0.3);
            margin-bottom: 0.75rem;
        }

        .sub-banner-tag svg {
            width: 12px;
            height: 12px;
        }

        .sub-banner-name {
            font-size: 2rem;
            font-weight: 900;
            letter-spacing: -1px;
            margin: 0 0 0.5rem;
            line-height: 1;
        }

        .sub-banner-meta {
            font-size: 0.85rem;
            opacity: 0.6;
            margin: 0;
        }

        /* Progress bar */
        .sub-progress {
            margin-top: 1.25rem;
            max-width: 400px;
        }

        .sub-progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            opacity: 0.5;
            margin-bottom: 6px;
        }

        .sub-progress-track {
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 99px;
            overflow: hidden;
        }

        .sub-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--sub-primary) 0%, var(--sub-accent) 100%);
            border-radius: 99px;
            transition: width 1s ease-out;
        }

        /* Stats row */
        .sub-stats-row {
            display: flex;
            gap: 2rem;
            margin-top: 1.25rem;
        }

        .sub-stat-item {
            text-align: center;
        }

        .sub-stat-value {
            font-size: 1.5rem;
            font-weight: 900;
            line-height: 1;
        }

        .sub-stat-label {
            font-size: 0.65rem;
            opacity: 0.5;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        /* Cancel btn */
        .sub-cancel-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .sub-cancel-btn:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* ===== HEADER ===== */
        .sub-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .sub-header h1 {
            font-size: 2.5rem;
            font-weight: 900;
            letter-spacing: -1.5px;
            line-height: 1.1;
            color: var(--sub-text);
            margin: 0 0 0.5rem;
        }

        .sub-header p {
            color: var(--sub-text-muted);
            font-size: 1rem;
            margin: 0 0 1.5rem;
        }

        /* ===== PERIOD SWITCHER ===== */
        .sub-switcher {
            display: inline-flex;
            align-items: center;
            background: var(--sub-surface);
            border: 1px solid var(--sub-border);
            padding: 4px;
            border-radius: 14px;
            box-shadow: var(--sub-shadow);
        }

        .sub-sw-btn {
            padding: 10px 24px;
            border-radius: 11px;
            border: none;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: transparent;
            color: var(--sub-text-muted);
        }

        .sub-sw-btn.active {
            background: var(--sub-primary);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .sub-sw-badge {
            display: inline-block;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 99px;
            margin-left: 8px;
            vertical-align: middle;
        }

        /* ===== PLAN CARDS ===== */
        .sub-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .sub-card {
            background: var(--sub-surface);
            border: 1px solid var(--sub-border);
            border-radius: var(--sub-radius);
            padding: 2rem;
            display: flex;
            flex-direction: column;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            position: relative;
            box-shadow: var(--sub-shadow);
        }

        .sub-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--sub-shadow-lg);
        }

        .sub-card.featured {
            border-color: var(--sub-primary);
            box-shadow: 0 0 0 1px var(--sub-primary), var(--sub-shadow-lg);
        }

        .sub-card-badge {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--sub-primary), var(--sub-accent));
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 4px 16px;
            border-radius: 99px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .sub-plan-name {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--sub-text);
            margin: 0 0 0.25rem;
        }

        .sub-plan-desc {
            font-size: 0.8rem;
            color: var(--sub-text-muted);
            margin: 0 0 1.25rem;
            line-height: 1.4;
        }

        .sub-price-row {
            display: flex;
            align-items: baseline;
            gap: 4px;
            margin-bottom: 0.25rem;
        }

        .sub-price {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--sub-text);
            letter-spacing: -1px;
            line-height: 1;
        }

        .sub-currency {
            font-size: 1rem;
            font-weight: 700;
            color: var(--sub-text-muted);
        }

        .sub-cycle {
            font-size: 0.8rem;
            color: var(--sub-text-muted);
            margin-bottom: 1.5rem;
        }

        .sub-divider {
            height: 1px;
            background: var(--sub-border);
            margin: 0 0 1.5rem;
        }

        /* Features */
        .sub-feat-list {
            list-style: none;
            padding: 0;
            margin: 0 0 1.5rem;
            flex-grow: 1;
        }

        .sub-feat-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 0;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--sub-text);
        }

        .sub-feat-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sub-feat-icon svg {
            width: 14px;
            height: 14px;
        }

        .sub-feat-icon.check {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .sub-feat-icon.star {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .sub-feat-icon.bolt {
            background: rgba(37, 99, 235, 0.1);
            color: var(--sub-primary);
        }

        /* CTA Buttons */
        .sub-cta {
            display: block;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .sub-cta-primary {
            background: linear-gradient(135deg, var(--sub-primary), var(--sub-accent));
            color: white;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.3);
        }

        .sub-cta-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(37, 99, 235, 0.4);
        }

        .sub-cta-secondary {
            background: var(--sub-surface-alt);
            color: var(--sub-text);
            border: 1px solid var(--sub-border);
        }

        .sub-cta-secondary:hover {
            background: var(--sub-primary);
            color: white;
            border-color: var(--sub-primary);
        }

        .sub-cta-current {
            background: var(--sub-surface-alt);
            color: var(--sub-text-muted);
            border: 2px dashed var(--sub-border);
            cursor: default;
        }

        /* ===== COMPARISON TABLE ===== */
        .sub-compare {
            background: var(--sub-surface);
            border: 1px solid var(--sub-border);
            border-radius: var(--sub-radius);
            overflow: hidden;
            margin-bottom: 3rem;
            box-shadow: var(--sub-shadow);
        }

        .sub-compare-title {
            padding: 1.25rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--sub-text);
            border-bottom: 1px solid var(--sub-border);
        }

        .sub-compare table {
            width: 100%;
            border-collapse: collapse;
        }

        .sub-compare th,
        .sub-compare td {
            padding: 12px 16px;
            text-align: center;
            font-size: 0.8rem;
            border-bottom: 1px solid var(--sub-border);
        }

        .sub-compare th:first-child,
        .sub-compare td:first-child {
            text-align: left;
            font-weight: 600;
            color: var(--sub-text);
        }

        .sub-compare th {
            font-weight: 800;
            color: var(--sub-text);
            background: var(--sub-surface-alt);
            font-size: 0.85rem;
        }

        .sub-compare td {
            color: var(--sub-text-muted);
        }

        .sub-compare tr:last-child td {
            border-bottom: none;
        }

        .sub-compare .check-icon {
            color: var(--sub-success);
        }

        .sub-compare .cross-icon {
            color: #cbd5e1;
        }

        /* ===== FAQ ===== */
        .sub-faq {
            margin-bottom: 2rem;
        }

        .sub-faq-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--sub-text);
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .sub-faq-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .sub-faq-item {
            background: var(--sub-surface);
            border: 1px solid var(--sub-border);
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--sub-shadow);
        }

        .sub-faq-q {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--sub-text);
            margin: 0 0 0.5rem;
        }

        .sub-faq-a {
            font-size: 0.8rem;
            color: var(--sub-text-muted);
            margin: 0;
            line-height: 1.5;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 900px) {
            .sub-grid {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }

            .sub-banner-inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .sub-faq-grid {
                grid-template-columns: 1fr;
            }

            .sub-compare {
                overflow-x: auto;
            }

            .sub-header h1 {
                font-size: 2rem;
            }

            .sub-stats-row {
                gap: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .sub-header h1 {
                font-size: 1.5rem;
                letter-spacing: -0.5px;
            }

            .sub-price {
                font-size: 2rem;
            }

            .sub-banner-name {
                font-size: 1.5rem;
            }

            .sub-cancel-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>

    <div class="sub-page">

        {{-- ===== ACTIVE SUBSCRIPTION BANNER ===== --}}
        @if($subscription && $subscription->isActive())
            <div class="sub-banner">
                <div class="sub-banner-inner">
                    <div>
                        <div class="sub-banner-tag">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Abonnement actif
                        </div>
                        <h2 class="sub-banner-name">{{ $subscription->plan->name }}</h2>
                        <p class="sub-banner-meta">
                            Actif depuis le {{ $subscription->starts_at->format('d/m/Y') }}
                            · Expire le {{ $subscription->ends_at->format('d/m/Y') }}
                        </p>

                        {{-- Progress bar --}}
                        <div class="sub-progress">
                            <div class="sub-progress-labels">
                                <span>{{ $subscription->starts_at->format('d M') }}</span>
                                <span>{{ $subscription->ends_at->format('d M Y') }}</span>
                            </div>
                            <div class="sub-progress-track">
                                <div class="sub-progress-fill" style="width: {{ $this->progress }}%"></div>
                            </div>
                        </div>

                        {{-- Quick stats --}}
                        <div class="sub-stats-row">
                            <div class="sub-stat-item">
                                <div class="sub-stat-value">{{ $subscription->daysRemaining() }}</div>
                                <div class="sub-stat-label">Jours restants</div>
                            </div>
                            <div class="sub-stat-item">
                                <div class="sub-stat-value">{{ $stats['total_boosted_ads'] ?? 0 }}</div>
                                <div class="sub-stat-label">Annonces boostées</div>
                            </div>
                            <div class="sub-stat-item">
                                <div class="sub-stat-value">+{{ $subscription->plan->boost_score }}</div>
                                <div class="sub-stat-label">Score boost</div>
                            </div>
                        </div>
                    </div>

                    <button wire:click="cancelSubscription"
                        wire:confirm="Êtes-vous sûr de vouloir résilier votre abonnement ? Cette action est irréversible."
                        class="sub-cancel-btn">
                        Résilier le forfait
                    </button>
                </div>
            </div>
        @endif

        {{-- ===== HEADER + PERIOD SWITCHER ===== --}}
        <div class="sub-header">
            <h1>Boostez votre agence</h1>
            <p>Choisissez le forfait adapté à vos ambitions et propulsez vos annonces en tête de liste.</p>

            <div class="sub-switcher">
                <button wire:click="setPeriod('monthly')"
                    class="sub-sw-btn {{ $period === 'monthly' ? 'active' : '' }}">
                    Mensuel
                </button>
                <button wire:click="setPeriod('yearly')" class="sub-sw-btn {{ $period === 'yearly' ? 'active' : '' }}">
                    Annuel
                    <span class="sub-sw-badge">-20%</span>
                </button>
            </div>
        </div>

        {{-- ===== PLAN CARDS ===== --}}
        <div class="sub-grid">
            @foreach($plans as $plan)
                @php
                    $price = $period === 'yearly' && $plan->price_yearly ? $plan->price_yearly : $plan->price;
                    $isCurrent = $subscription?->subscription_plan_id === $plan->id && $subscription->isActive();
                    $isFeatured = $loop->index === 1; // Middle plan = featured
                @endphp

                <div class="sub-card {{ $isFeatured ? 'featured' : '' }}">
                    @if($isFeatured)
                        <div class="sub-card-badge">🔥 Le plus populaire</div>
                    @endif

                    <div class="sub-plan-name">{{ $plan->name }}</div>
                    <div class="sub-plan-desc">{{ $plan->description }}</div>

                    <div class="sub-price-row">
                        <span class="sub-price">{{ number_format($price, 0, ',', ' ') }}</span>
                        <span class="sub-currency">FCFA</span>
                    </div>
                    <div class="sub-cycle">par {{ $period === 'monthly' ? 'mois' : 'an' }}</div>

                    <div class="sub-divider"></div>

                    <ul class="sub-feat-list">
                        @foreach($plan->features ?? [] as $feature)
                            <li class="sub-feat-item">
                                <span class="sub-feat-icon check">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                {{ $feature }}
                            </li>
                        @endforeach

                        {{-- Boost feature --}}
                        <li class="sub-feat-item">
                            <span class="sub-feat-icon bolt">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </span>
                            <strong>Boost +{{ $plan->boost_score }} pts</strong> pendant {{ $plan->boost_duration_days }}j
                        </li>

                        {{-- Max ads --}}
                        <li class="sub-feat-item">
                            <span class="sub-feat-icon star">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </span>
                            {{ $plan->max_ads ? $plan->max_ads . ' annonces max' : 'Annonces illimitées' }}
                        </li>
                    </ul>

                    @if($isCurrent)
                        <div class="sub-cta sub-cta-current">✓ Votre forfait actuel</div>
                    @else
                        <button wire:click="subscribe('{{ $plan->id }}')" wire:loading.attr="disabled"
                            class="sub-cta {{ $isFeatured ? 'sub-cta-primary' : 'sub-cta-secondary' }}">
                            <span wire:loading.remove wire:target="subscribe('{{ $plan->id }}')">
                                Choisir {{ $plan->name }}
                            </span>
                            <span wire:loading wire:target="subscribe('{{ $plan->id }}')">
                                Redirection...
                            </span>
                        </button>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- ===== COMPARISON TABLE ===== --}}
        <div class="sub-compare">
            <div class="sub-compare-title">📊 Comparaison détaillée</div>
            <table>
                <thead>
                    <tr>
                        <th>Fonctionnalité</th>
                        @foreach($plans as $plan)
                            <th>{{ $plan->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Annonces</td>
                        @foreach($plans as $plan)
                            <td>{{ $plan->max_ads ?? '∞' }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td>Score Boost</td>
                        @foreach($plans as $plan)
                            <td><strong>+{{ $plan->boost_score }}</strong> pts</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td>Durée du Boost</td>
                        @foreach($plans as $plan)
                            <td>{{ $plan->boost_duration_days }} jours</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td>Badge agence</td>
                        @foreach($plans as $plan)
                            @php $hasBadge = collect($plan->features ?? [])->contains(fn($f) => str_contains(strtolower($f), 'badge')); @endphp
                            <td>
                                @if($hasBadge)
                                    <span class="check-icon">✓</span>
                                @else
                                    <span class="cross-icon">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td>Support prioritaire</td>
                        @foreach($plans as $plan)
                            @php $hasSupport = collect($plan->features ?? [])->contains(fn($f) => str_contains(strtolower($f), 'prioritaire') || str_contains(strtolower($f), '24/7')); @endphp
                            <td>
                                @if($hasSupport)
                                    <span class="check-icon">✓</span>
                                @else
                                    <span class="cross-icon">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td>Statistiques avancées</td>
                        @foreach($plans as $plan)
                            @php $hasStats = collect($plan->features ?? [])->contains(fn($f) => str_contains(strtolower($f), 'statistiques')); @endphp
                            <td>
                                @if($hasStats)
                                    <span class="check-icon">✓</span>
                                @else
                                    <span class="cross-icon">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td>API dédiée</td>
                        @foreach($plans as $plan)
                            @php $hasApi = collect($plan->features ?? [])->contains(fn($f) => str_contains(strtolower($f), 'api')); @endphp
                            <td>
                                @if($hasApi)
                                    <span class="check-icon">✓</span>
                                @else
                                    <span class="cross-icon">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ===== FAQ ===== --}}
        <div class="sub-faq">
            <div class="sub-faq-title">Questions fréquentes</div>
            <div class="sub-faq-grid">
                <div class="sub-faq-item">
                    <div class="sub-faq-q">Puis-je changer de plan à tout moment ?</div>
                    <div class="sub-faq-a">Oui, vous pouvez passer à un plan supérieur à tout moment. Votre ancien
                        abonnement sera remplacé immédiatement après le paiement.</div>
                </div>
                <div class="sub-faq-item">
                    <div class="sub-faq-q">Comment fonctionne le boost ?</div>
                    <div class="sub-faq-a">Le boost augmente le score de visibilité de vos annonces, les faisant
                        apparaître en priorité dans les résultats de recherche pendant la durée indiquée.</div>
                </div>
                <div class="sub-faq-item">
                    <div class="sub-faq-q">Quand mon abonnement sera-t-il activé ?</div>
                    <div class="sub-faq-a">Votre abonnement est activé instantanément après confirmation du paiement via
                        FedaPay. Vous recevrez un email de confirmation avec votre facture.</div>
                </div>
                <div class="sub-faq-item">
                    <div class="sub-faq-q">Que se passe-t-il à l'expiration ?</div>
                    <div class="sub-faq-a">Vos annonces restent en ligne mais perdent leur boost de visibilité. Vous
                        pouvez renouveler votre abonnement à tout moment pour retrouver les avantages.</div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
