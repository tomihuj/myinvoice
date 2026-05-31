-- Slovak VAT rates (Stage 1). Additive seed only; schema unchanged.
-- Effective Slovak rates 2025 -> applied 2026: standard 23, reduced 19, reduced 5, exempt 0, reverse charge.
INSERT IGNORE INTO vat_rates
  (code, rate_percent, country, label_cs, label_en, is_default, is_reverse_charge, valid_from, display_order)
VALUES
  ('SK-23', 23.00, 'SK', 'Základná 23 %', 'Standard 23 %',  1, 0, '2025-01-01', 10),
  ('SK-19', 19.00, 'SK', 'Znížená 19 %',  'Reduced 19 %',   0, 0, '2025-01-01', 20),
  ('SK-5',   5.00, 'SK', 'Znížená 5 %',   'Reduced 5 %',    0, 0, '2025-01-01', 30),
  ('SK-0',   0.00, 'SK', 'Oslobodené',    'Exempt',         0, 0, '2025-01-01', 40),
  ('SK-RC',  0.00, 'SK', 'Prenesenie daňovej povinnosti', 'Reverse charge', 0, 1, '2025-01-01', 50);
