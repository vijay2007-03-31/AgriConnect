/**
 * AgriConnect — Smart Contract Farming & Agritech Management Portal
 * Module: estimator.js (Interactive Crop Yield, Production Cost & Net Profit Calculator)
 *
 * Implements agricultural agronomic and econometric models to project harvest yield,
 * direct cultivation expenditures, gross revenues, and net profits in Indian Rupees.
 */

const CROP_BENCHMARKS = {
    wheat: {
        name: 'Wheat (Gehun)',
        season: 'Rabi',
        avgYieldPerAcre: 18.5,       // Quintals per acre
        avgCostPerAcre: 21500,       // INR per acre (seeds, irrigation, fertilizer, labor)
        defaultMsp: 2450,            // Official MSP / Market reference (INR / Qtl)
        irrigationNeeds: 'Moderate (Canal/Tube well)',
        durationDays: 130
    },
    rice: {
        name: 'Paddy / Basmati Rice (Dhan)',
        season: 'Kharif',
        avgYieldPerAcre: 24.0,
        avgCostPerAcre: 29000,
        defaultMsp: 2320,
        irrigationNeeds: 'High (Standing water)',
        durationDays: 145
    },
    maize: {
        name: 'Maize / Corn (Makka)',
        season: 'Kharif / Rabi',
        avgYieldPerAcre: 22.0,
        avgCostPerAcre: 22000,
        defaultMsp: 2090,
        irrigationNeeds: 'Low-to-Medium',
        durationDays: 100
    },
    cotton: {
        name: 'Cotton (Kapas / Shankar-6)',
        season: 'Kharif',
        avgYieldPerAcre: 10.5,
        avgCostPerAcre: 28500,
        defaultMsp: 7121,
        irrigationNeeds: 'Medium (Drip recommended)',
        durationDays: 160
    },
    mustard: {
        name: 'Mustard / Rapeseed (Sarson)',
        season: 'Rabi',
        avgYieldPerAcre: 8.5,
        avgCostPerAcre: 17500,
        defaultMsp: 5650,
        irrigationNeeds: 'Low',
        durationDays: 110
    },
    soybean: {
        name: 'Soybean (Bhatma)',
        season: 'Kharif',
        avgYieldPerAcre: 12.0,
        avgCostPerAcre: 19500,
        defaultMsp: 4892,
        irrigationNeeds: 'Rainfed / Low',
        durationDays: 95
    },
    coconut: {
        name: 'Coconut / Thengai (Pollachi Tall)',
        season: 'Perennial (Year-round)',
        avgYieldPerAcre: 36.0,
        avgCostPerAcre: 34000,
        defaultMsp: 3850,
        irrigationNeeds: 'High (Basin / Drip irrigation)',
        durationDays: 365
    },
    onion: {
        name: 'Onion / Pyaz / Vengayam (Nashik Red)',
        season: 'Rabi / Kharif',
        avgYieldPerAcre: 115.0,
        avgCostPerAcre: 54000,
        defaultMsp: 2600,
        irrigationNeeds: 'Moderate (Sprinkler / Drip)',
        durationDays: 115
    }
};

class CropEstimator {
    constructor() {
        this.cropSelect = document.getElementById('calc-crop');
        this.acresInput = document.getElementById('calc-acres');
        this.acresSlider = document.getElementById('calc-acres-slider');
        this.priceInput = document.getElementById('calc-price');
        this.farmingTypeSelect = document.getElementById('calc-farming-type');

        // Output Display Elements
        this.outYield = document.getElementById('out-yield');
        this.outCost = document.getElementById('out-cost');
        this.outRevenue = document.getElementById('out-revenue');
        this.outProfit = document.getElementById('out-profit');
        this.outRoi = document.getElementById('out-roi');
        this.outMargin = document.getElementById('out-margin');
        this.cropSeasonBadge = document.getElementById('crop-season-badge');
        this.cropIrrigationNote = document.getElementById('crop-irrigation-note');

        if (this.cropSelect && this.acresInput) {
            this.init();
        }
    }

    init() {
        // Sync Acres Slider and Input Box
        if (this.acresSlider && this.acresInput) {
            this.acresSlider.addEventListener('input', (e) => {
                this.acresInput.value = e.target.value;
                this.calculate();
            });
            this.acresInput.addEventListener('input', (e) => {
                this.acresSlider.value = e.target.value;
                this.calculate();
            });
        }

        // On crop change, auto-fill default MSP and update notes
        this.cropSelect.addEventListener('change', () => {
            const cropKey = this.cropSelect.value;
            const crop = CROP_BENCHMARKS[cropKey];
            if (crop && this.priceInput) {
                this.priceInput.value = crop.defaultMsp;
                if (this.cropSeasonBadge) {
                    this.cropSeasonBadge.textContent = `${crop.season} Crop (${crop.durationDays} Days)`;
                }
                if (this.cropIrrigationNote) {
                    this.cropIrrigationNote.textContent = `Irrigation: ${crop.irrigationNeeds} | Benchmark: ${crop.avgYieldPerAcre} Qtl/Acre`;
                }
            }
            this.calculate();
        });

        if (this.priceInput) {
            this.priceInput.addEventListener('input', () => this.calculate());
        }

        if (this.farmingTypeSelect) {
            this.farmingTypeSelect.addEventListener('change', () => this.calculate());
        }

        // Quick Land Preset Buttons
        const presetButtons = document.querySelectorAll('.land-preset-btn');
        presetButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const acres = e.target.dataset.acres;
                if (acres && this.acresInput) {
                    this.acresInput.value = acres;
                    if (this.acresSlider) this.acresSlider.value = acres;
                    this.calculate();
                }
            });
        });

        // Initial trigger
        this.calculate();
    }

    /**
     * Executes econometric formulas and updates UI
     */
    calculate() {
        const cropKey = this.cropSelect.value || 'wheat';
        const crop = CROP_BENCHMARKS[cropKey] || CROP_BENCHMARKS.wheat;
        const acres = parseFloat(this.acresInput.value) || 0;
        const pricePerQuintal = parseFloat(this.priceInput.value) || crop.defaultMsp;
        const farmingType = this.farmingTypeSelect ? this.farmingTypeSelect.value : 'conventional';

        // Organic Farming multiplier: -10% yield initially, +25% market premium, +5% natural input cost
        let yieldModifier = 1.0;
        let costModifier = 1.0;
        let priceModifier = 1.0;

        if (farmingType === 'organic') {
            yieldModifier = 0.92;
            costModifier = 1.08;
            priceModifier = 1.25; // 25% organic export price bonus
        } else if (farmingType === 'precision') {
            yieldModifier = 1.15; // 15% yield boost from precision drip & sensor agronomy
            costModifier = 1.10;
        }

        const effectiveYieldPerAcre = crop.avgYieldPerAcre * yieldModifier;
        const effectiveCostPerAcre = crop.avgCostPerAcre * costModifier;
        const effectivePricePerQtl = pricePerQuintal * priceModifier;

        // Core Aggregations
        const totalYieldQuintals = Math.round(acres * effectiveYieldPerAcre * 10) / 10;
        const totalCostINR = Math.round(acres * effectiveCostPerAcre);
        const grossRevenueINR = Math.round(totalYieldQuintals * effectivePricePerQtl);
        const netProfitINR = grossRevenueINR - totalCostINR;

        // Percentage Metrics
        const profitMargin = grossRevenueINR > 0 ? ((netProfitINR / grossRevenueINR) * 100).toFixed(1) : 0;
        const roi = totalCostINR > 0 ? ((netProfitINR / totalCostINR) * 100).toFixed(1) : 0;

        // DOM Updates with Indian Number Formatting
        if (this.outYield) this.outYield.textContent = `${totalYieldQuintals.toLocaleString('en-IN')} Quintals`;
        if (this.outCost) this.outCost.textContent = this.formatCurrency(totalCostINR);
        if (this.outRevenue) this.outRevenue.textContent = this.formatCurrency(grossRevenueINR);
        
        if (this.outProfit) {
            this.outProfit.textContent = this.formatCurrency(netProfitINR);
            if (netProfitINR < 0) {
                this.outProfit.style.color = '#ef4444';
            } else {
                this.outProfit.style.color = '#4ade80';
            }
        }

        if (this.outRoi) {
            this.outRoi.textContent = `${roi}%`;
        }
        if (this.outMargin) {
            this.outMargin.textContent = `${profitMargin}%`;
        }
    }

    /**
     * Formats number to Indian Rupee notation (e.g. ₹ 2,45,000)
     * @param {number} amount
     * @returns {string}
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 0
        }).format(amount);
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new CropEstimator();
});
