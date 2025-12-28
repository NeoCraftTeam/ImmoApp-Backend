<x-filament-panels::page>
    <!-- Import Modern Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">

    <style>
        #pricing-app {
            font-family: 'Outfit', sans-serif;
            --primary: #3b82f6;
            --secondary: #8b5cf6;
            --accent: #ec4899;
            --surface: rgba(255, 255, 255, 0.85);
            --text: #1e293b;
            padding: 2rem 0;
            position: relative;
            overflow: hidden;
        }

        /* Background Blobs for Visual Interest */
        .bg-blob {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, rgba(139, 92, 246, 0.05) 100%);
            filter: blur(80px);
            z-index: -1;
            border-radius: 50%;
        }
        .blob-1 { top: -100px; right: -100px; }
        .blob-2 { bottom: -100px; left: -100px; background: radial-gradient(circle, rgba(236, 72, 153, 0.1) 0%, transparent 100%); }

        /* Header Design */
        .header-box {
            text-align: center;
            margin-bottom: 3rem;
        }

        .header-box h1 {
            font-size: 3rem;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -2px;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #1e293b 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Period Switcher */
        .sw-container {
            display: inline-flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 14px;
        }
        .sw-btn {
            padding: 8px 24px;
            border-radius: 11px;
            border: none;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Pricing Cards layout - Forced 3 columns */
        .grid-layout {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            max-width: 1100px;
            margin: 0 auto;
        }

        .card-wrap {
            background: var(--surface);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 24px;
            padding: 2rem;
            position: relative;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
        }

        .card-wrap:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.08);
        }

        .card-wrap.featured::after {
            top: 1rem;
            right: 1.5rem;
            font-size: 0.6rem;
            padding: 3px 10px;
        }

        .p-name { font-size: 1.4rem; font-weight: 800; margin-bottom: 0.25rem; color: #0f172a; }
        .p-price { font-size: 2.5rem; font-weight: 900; color: #0f172a; letter-spacing: -1px; }
        .p-curr { font-size: 0.9rem; color: #64748b; font-weight: 600; }
        .p-cycle { font-size: 0.85rem; color: #94a3b8; }

        .feat-list { list-style: none; padding: 0; margin: 2rem 0; flex-grow: 1; }
        .feat-item { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; font-size: 0.875rem; font-weight: 500; color: #334155; }
        .feat-ico { width: 20px; height: 20px; flex-shrink: 0; background: #f1f5f9; color: var(--primary); border-radius: 6px; padding: 4px; }

        .action-btn {
            width: 100%;
            padding: 14px;
            border-radius: 14px;
            font-size: 0.95rem;
        }
        .btn-grad {
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: 0 15px 30px rgba(59, 130, 246, 0.3);
        }
        .btn-grad:hover { transform: scale(1.02); box-shadow: 0 20px 40px rgba(59, 130, 246, 0.4); }
        .btn-ghost { background: #f1f5f9; color: #1e293b; }
        .btn-curr { background: #f8fafc; color: #94a3b8; border: 2px dashed #e2e8f0; cursor: default; }

        .sw-btn.active {
            background: #fff;
            color: var(--primary);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .sw-btn.inactive { color: #64748b; background: transparent; }

        /* Current Plan Dashboard - Compact & Sharp */
        .current-badge-container {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 20px;
            padding: 1.25rem 2rem;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: white;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255,255,255,0.08);
            position: relative;
            overflow: hidden;
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .current-badge-container::after {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: var(--primary);
            filter: blur(70px);
            opacity: 0.15;
        }

        .cur-title { font-size: 1.8rem; font-weight: 900; letter-spacing: -1px; margin: 0; line-height: 1; }
        .cur-info { opacity: 0.6; font-size: 0.85rem; margin-top: 4px; }
        .cur-tag {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 800;
            margin-bottom: 0.4rem;
            display: inline-block;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .cur-cancel-btn {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.6);
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            position: relative;
            z-index: 2;
        }
        .cur-cancel-btn:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.2);
        }

        @media (max-width: 768px) {
            .header-box h1 { font-size: 3rem; }
            .cur-title { font-size: 2.5rem; }
            .current-badge-container { flex-direction: column; text-align: center; gap: 2rem; }
        }
    </style>

    <div id="pricing-app">
        <div class="bg-blob blob-1"></div>
        <div class="bg-blob blob-2"></div>

        @if($subscription && $subscription->isActive())
            <div class="current-badge-container">
                <div>
                    <span class="cur-tag">VOTRE PACK ACTUEL</span>
                    <h2 class="cur-title">{{ $subscription->plan->name }}</h2>
                    <p class="cur-info">Expire le {{ $subscription->ends_at->format('d/m/Y') }} ({{ $subscription->daysRemaining() }} jours)</p>
                </div>
                <button wire:click="cancelSubscription" wire:confirm="Confirmer la résiliation ?" class="cur-cancel-btn">
                    Résilier le forfait
                </button>
            </div>
        @endif

        <div class="header-box">
            <h1>Boostez votre visibilité.</h1>
            <div class="sw-container">
                <button wire:click="setPeriod('monthly')" class="sw-btn {{ $period === 'monthly' ? 'active' : 'inactive' }}">Mensuel</button>
                <button wire:click="setPeriod('yearly')" class="sw-btn {{ $period === 'yearly' ? 'active' : 'inactive' }}">Annuel (-20%)</button>
            </div>
        </div>

        <div class="grid-layout">
            @foreach($plans as $plan)
                @php 
                    $price = $period === 'yearly' ? $plan->price_yearly : $plan->price;
                    $isCurrent = $subscription?->plan_id === $plan->id && $subscription->isActive();
                    $isFeatured = $plan->name === 'Premium';
                @endphp

                <div class="card-wrap {{ $isFeatured ? 'featured' : '' }}">
                    <h3 class="p-name">{{ $plan->name }}</h3>
                    <div class="p-price">
                        {{ number_format($price, 0, ',', ' ') }}<span class="p-curr">FCFA</span>
                    </div>
                    <span class="p-cycle">par {{ $period === 'monthly' ? 'mois' : 'an' }}</span>

                    <ul class="feat-list">
                        @foreach($plan->features ?? [] as $feature)
                            <li class="feat-item">
                                <svg class="feat-ico" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                {{ $feature }}
                            </li>
                        @endforeach
                        <li class="feat-item" style="color: var(--primary); font-weight: 700;">
                            <svg class="feat-ico" style="background: rgba(59,130,246,0.1);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            Boost Turbo +{{ $plan->boost_score }} pts
                        </li>
                    </ul>

                    @if($isCurrent)
                        <div class="action-btn btn-curr">PACK ACTUEL</div>
                    @else
                        <button 
                            wire:click="subscribe('{{ $plan->id }}')" 
                            class="action-btn {{ $isFeatured ? 'btn-grad' : 'btn-ghost' }}"
                            wire:loading.attr="disabled"
                        >
                            Démarrer maintenant
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
