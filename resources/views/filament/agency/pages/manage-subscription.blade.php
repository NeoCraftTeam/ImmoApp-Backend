<x-filament-panels::page>
    <style>
        #forge-pricing-page {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1a202c;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .pricing-header {
            text-align: center;
            margin-bottom: 3.5rem;
        }

        .pricing-header h1 {
            font-size: 1.125rem;
            color: #718096;
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        /* Toggle System */
        .period-toggle {
            display: inline-flex;
            background: #edf2f7;
            padding: 0.25rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
        }

        .period-toggle button {
            padding: 0.5rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 0.5rem;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .period-toggle .active {
            background: white;
            color: #1a202c;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .period-toggle .inactive {
            background: transparent;
            color: #718096;
        }

        /* Grid */
        .pricing-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        @media (min-width: 768px) {
            .pricing-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* Card Decoration */
        .pricing-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .pricing-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .recommended-badge {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: #ebf8ff;
            color: #3182ce;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            border: 1px solid #bee3f8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .plan-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #111827;
        }

        .plan-desc {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 2rem;
            line-height: 1.5;
            min-height: 3rem;
        }

        .plan-price {
            margin-bottom: 2.5rem;
            display: flex;
            align-items: baseline;
        }

        .price-amount {
            font-size: 2.5rem;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.025em;
        }

        .price-currency {
            font-size: 1rem;
            font-weight: 600;
            color: #6b7280;
            margin-left: 0.25rem;
        }

        .price-period {
            font-size: 0.875rem;
            color: #9ca3af;
            margin-left: 0.25rem;
        }

        /* Features */
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0 0 2.5rem 0;
            flex-grow: 1;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #4b5563;
        }

        .feature-icon {
            width: 1.25rem;
            height: 1.25rem;
            flex-shrink: 0;
            margin-top: 0.125rem;
        }

        .icon-check { color: #10b981; }
        .icon-x { color: #f43f5e; opacity: 0.5; }
        .icon-boost { color: #3b82f6; }

        .feature-boost {
            font-weight: 700;
            color: #111827;
        }

        /* Buttons */
        .subscribe-btn {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            display: block;
        }

        .btn-primary {
            background: #111827;
            color: white;
            border-color: #111827;
        }

        .btn-primary:hover {
            background: #1f2937;
        }

        .btn-outline {
            background: white;
            color: #374151;
        }

        .btn-outline:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .btn-current {
            background: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
        }

        .footer-note {
            text-align: center;
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 4rem;
        }

        /* Dark mode compatibility */
        @media (prefers-color-scheme: dark) {
            .pricing-card { background: #131314; border-color: #2e2e30; }
            .plan-name, .price-amount, .feature-boost { color: #f9fafb; }
            .period-toggle { background: #1f1f21; border-color: #2e2e30; }
            .period-toggle .active { background: #2d2d30; color: white; }
            .btn-outline { background: transparent; color: #d1d5db; border-color: #3f3f42; }
            .btn-primary { background: #3b82f6; border-color: #3b82f6; }
        }
    </style>

    <div id="forge-pricing-page">
        @if(request('status') === 'approved')
            <div class="success-banner" style="background: #ecfdf5; border: 1px solid #10b981; border-radius: 1rem; padding: 2rem; text-align: center; margin-bottom: 3rem; animation: slideDown 0.5s ease-out;">
                <div style="background: #10b981; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h2 style="font-size: 1.5rem; font-weight: 800; color: #064e3b; margin-bottom: 0.5rem;">Paiement Confirmé !</h2>
                <p style="color: #047857; font-weight: 500;">Votre abonnement est maintenant actif. Profitez de tous vos avantages.</p>
            </div>
            <style>
                @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
            </style>
        @endif

        @if($subscription && $subscription->isActive())
            <div class="current-subscription-card" style="background: #111827; color: white; border-radius: 1rem; padding: 2.5rem; margin-bottom: 4rem; position: relative; overflow: hidden;">
                <div style="position: absolute; top: -10%; right: -5%; opacity: 0.1;">
                    <svg width="200" height="200" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L1 21h22L12 2zm0 3.45l8.15 14.1H3.85L12 5.45z"/></svg>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 1;">
                    <div>
                        <div style="background: #3b82f6; color: white; font-size: 0.65rem; font-weight: 800; padding: 0.25rem 0.75rem; border-radius: 9999px; display: inline-block; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">Plan Actif</div>
                        <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;">{{ $subscription->plan->name }}</h2>
                        <p style="color: #9ca3af; font-size: 0.875rem;">Prochaine facturation : <strong>{{ $subscription->ends_at->format('d/m/Y') }}</strong> ({{ $subscription->daysRemaining() }} jours restants)</p>
                    </div>
                    
                    <button 
                        wire:click="cancelSubscription" 
                        wire:confirm="Êtes-vous sûr de vouloir annuler votre abonnement ? Vous conserverez vos avantages jusqu'à la fin de la période."
                        style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s;"
                        onmouseover="this.style.background='rgba(239,68,68,0.2)'; this.style.borderColor='rgba(239,68,68,0.4)';"
                        onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';"
                    >
                        Annuler l'abonnement
                    </button>
                </div>
            </div>
        @endif

        <div class="pricing-header">
            <h1>{{ ($subscription && $subscription->isActive()) ? 'Changer de formule' : 'Tarification simple et transparente.' }}</h1>
            
            <div class="period-toggle">
                <button 
                    wire:click="setPeriod('monthly')" 
                    class="{{ $period === 'monthly' ? 'active' : 'inactive' }}"
                >
                    Mensuel
                </button>
                <button 
                    wire:click="setPeriod('yearly')" 
                    class="{{ $period === 'yearly' ? 'active' : 'inactive' }}"
                >
                    Annuel
                </button>
            </div>
        </div>

        <div class="pricing-grid">
            @foreach($plans as $plan)
                @php 
                    $price = $period === 'yearly' ? $plan->price_yearly : $plan->price;
                    $isCurrent = $subscription?->plan_id === $plan->id && $subscription->isActive();
                    $isRecommended = $plan->name === 'Premium';
                @endphp

                <div class="pricing-card">
                    @if($isRecommended)
                        <div class="recommended-badge">Recommandé</div>
                    @endif

                    <div class="plan-name">{{ $plan->name }}</div>
                    <div class="plan-desc">{{ $plan->description }}</div>

                    <div class="plan-price">
                        <span class="price-amount">{{ number_format($price, 0, ',', ' ') }}</span>
                        <span class="price-currency">FCFA</span>
                        <span class="price-period">/ {{ $period === 'monthly' ? 'mois' : 'an' }}</span>
                    </div>

                    <ul class="feature-list">
                        @foreach($plan->features ?? [] as $feature)
                            <li class="feature-item">
                                <svg class="feature-icon icon-check" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                {{ $feature }}
                            </li>
                        @endforeach

                        <li class="feature-item">
                            <svg class="feature-icon icon-boost" fill="currentColor" viewBox="0 0 20 20"><path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM5.884 6.607a1 1 0 01-.226 1.396l-1 1a1 1 0 11-1.316-1.498l1-1a1 1 0 011.542.102zM14.116 6.607a1 1 0 00.226 1.396l1 1a1 1 0 101.316-1.498l-1-1a1 1 0 00-1.542.102zM12.614 10a2.614 2.614 0 11-5.228 0 2.614 2.614 0 015.228 0zM12.94 14.332a.5.5 0 01.474.312l.142.366c.105.27.3.504.55.658l.19.116a.5.5 0 01.127.766l-.16.19a.5.5 0 01-.715.056l-.133-.105a.5.5 0 01-.362-.178l-.134-.16a.5.5 0 00-.766.127l-.116.19a.5.5 0 01-.658.55l-.366-.142a.5.5 0 01-.312-.474V16.5a.5.5 0 01.312-.474l.366-.142c.27-.105.504-.3.658-.55l.116-.19a.5.5 0 01.766-.127l.16.19c.148.175.4.248.618.173z"></path></svg>
                            <span class="feature-boost">Boost Automatique +{{ $plan->boost_score }} pts</span>
                        </li>

                        @if($plan->name === 'Basic')
                            <li class="feature-item" style="opacity: 0.5;">
                                <svg class="feature-icon icon-x" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                                Support prioritaire 24h/7
                            </li>
                        @endif
                    </ul>

                    @if($isCurrent)
                        <div class="subscribe-btn btn-current">PLAN ACTUEL</div>
                    @else
                        <button 
                            wire:click="subscribe('{{ $plan->id }}')" 
                            class="subscribe-btn {{ $isRecommended ? 'btn-primary' : 'btn-outline' }}"
                            wire:loading.attr="disabled"
                        >
                            Continuer avec {{ $plan->name }}
                        </button>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="footer-note">
            Paiements sécurisés par <strong>FedaPay</strong>. Des questions ? Contactez notre support.
        </div>
    </div>
</x-filament-panels::page>
