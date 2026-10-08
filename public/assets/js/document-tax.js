(function (global) {
  "use strict";

  function normalizeRegime(value) {
    let regime = value;
    if (typeof regime === "string") {
      try {
        regime = JSON.parse(regime);
      } catch (_) {
        // The regime may be stored as plain text.
      }
    }
    if (regime && typeof regime === "object") {
      regime = regime.regime || regime.name || regime.value || "";
    }
    return String(regime || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .trim()
      .toLowerCase();
  }

  function isGeneralRegime(value) {
    const regime = normalizeRegime(value);
    return !regime || regime.includes("geral");
  }

  function effectiveRate(rate, regime) {
    const numericRate = Number(rate) || 0;
    if (!isGeneralRegime(regime) || numericRate === 7) return 0;
    return numericRate;
  }

  function calculateItem(item, regime) {
    const quantity = Number(item.quantity) || 0;
    const unitPrice = Number(item.unit_price) || 0;
    const base = unitPrice * quantity;
    const discount = Math.min(
      base,
      base * ((Number(item.discount) || 0) / 100),
    );
    const taxableBase = base - discount;
    const taxRate = effectiveRate(
      item.tax ?? item.tax_rate ?? item.tax_percentage ?? 0,
      regime,
    );
    const tax = taxableBase * (taxRate / 100);

    return {
      base,
      discount,
      taxableBase,
      taxRate,
      tax,
      total: taxableBase + tax,
    };
  }

  function summarize(items, regime, retentionTotal = 0) {
    const groups = new Map();
    let totalSum = 0;
    let totalDiscount = 0;
    let totalTax = 0;

    (Array.isArray(items) ? items : []).forEach((item) => {
      const calc = calculateItem(item, regime);
      const key = calc.taxRate;
      const group = groups.get(key) || {
        rate: key,
        base: 0,
        iva: 0,
        retention: 0,
        net: 0,
      };

      totalSum += calc.base;
      totalDiscount += calc.discount;
      totalTax += calc.tax;
      group.base += calc.taxableBase;
      group.iva += calc.tax;
      group.net += calc.taxableBase + calc.tax;
      groups.set(key, group);
    });

    const retention = Math.max(0, Number(retentionTotal) || 0);
    const taxableBaseTotal = Array.from(groups.values()).reduce(
      (sum, group) => sum + group.base,
      0,
    );
    let allocatedRetention = 0;
    const rows = Array.from(groups.values())
      .sort((a, b) => a.rate - b.rate)
      .map((group, index, all) => {
        const share =
          taxableBaseTotal > 0
            ? index === all.length - 1
              ? retention - allocatedRetention
              : (retention * group.base) / taxableBaseTotal
            : 0;
        allocatedRetention += share;
        group.retention = share;
        group.net -= share;
        return group;
      });

    return {
      rows,
      totalSum,
      totalDiscount,
      subtotal: totalSum - totalDiscount,
      totalTax,
      retention,
      finalTotal: Math.max(0, totalSum - totalDiscount + totalTax - retention),
    };
  }

  global.BXDocumentTax = Object.freeze({
    normalizeRegime,
    isGeneralRegime,
    effectiveRate,
    calculateItem,
    summarize,
  });
})(window);
