# NCDEX & MCX Market Source Contract Audit & Integration Architecture Plan

**Document Version**: 2.1.0  
**Audit Date**: September 17, 2026  
**Status**: READ-ONLY AUDIT & OFFICIAL SOURCE SPECIFICATION (NO CODE MODIFICATIONS / NO FIXTURE FABRICATION)  
**Target Module**: `NCDEX + MCX Market Source Integration`

---

## 1. Executive Summary & Domain Invariants

This document provides the definitive, corrected exchange source contract audit and architectural integration plan for connecting the **National Commodity & Derivatives Exchange (NCDEX)** and the **Multi Commodity Exchange of India (MCX)** to **Vyapari Darbaar**.

### 1.1 Invariant Architecture Hierarchy
```
Commodity (Physical Domain Taxonomy)
   ↓
ExchangeCommodityMapping (Exchange-to-Commodity Translation)
   ↓
ExchangeInstrument (Tradable Financial Derivative Contract)
   ↓
MarketBhavcopy (Daily EOD Price, Turnover & OI Session History)
```

### 1.2 Verification of Existing Database & Code Foundations
- **`exchange_instruments` Table**:
  - Enforces composite database foreign key: `(exchange_commodity_mapping_id, exchange_id) -> exchange_commodity_mappings(id, exchange_id)` with `RESTRICT` on delete.
  - Enforces `UNIQUE(exchange_id, external_instrument_id)`.
  - Uses `lifecycle_status` enum (`active`, `expired`, `delisted`, `suspended`) and boolean `is_enabled`.
  - Has **NO SoftDeletes**.
- **`market_bhavcopies` Table**:
  - Enforces `UNIQUE(exchange_instrument_id, trade_date)` (`uk_bhavcopy_instrument_date`).
  - Contains **NO** denormalized `commodity_id` or `exchange_id`.
  - Numeric prices use `DECIMAL(20,8)` (nullable, no default 0).
  - Quantity & OI use `DECIMAL(20,6)` (nullable). Traded value uses `DECIMAL(24,6)` (nullable). Number of trades uses `unsignedBigInteger` (nullable).
- **`market_ingestion_runs` Table**:
  - Tracks `exchange_id`, `source_type`, `trade_date`, `source_file_name`, `source_checksum` (SHA-256), `storage_path`, `status`, counters (`records_received`, `records_inserted`, `records_updated`, `records_skipped`, `records_failed`), timestamps (`started_at`, `finished_at`), and `error_summary` (JSON).

---

## 2. Comprehensive Repository Search & Evidence Audit

### 2.1 File & Content Search Results
A recursive search across all directories (`docs/`, `tests/`, `storage/`, `resources/`, `database/`, `app/`, `config/`) for `*.json`, `*.har`, `*.http`, `*.rest`, `*.postman*`, `*.log`, and exchange keywords (`ncdex`, `mcx`, `FinInstrmId`, `TckrSymb`, `InstrumentID`, `Token`, `LTP`, `bhav`, `marketwatch`) revealed:

1. **JSON Files Present in Repo**:
   - `composer.json` (Project PHP dependencies)
   - `package.json` (Project JS dependencies)
   - `docs/postman/Vyapari-Darbaar.postman_collection.json` (Postman API collection - 380 KB)
   - `docs/postman/Vyapari-Darbaar.postman_environment.json` (Postman environment config)
2. **Exchange JSON Fixtures / Payloads**:
   - **NO NCDEX API JSON FIXTURE PRESENT**
   - **NO MCX API JSON FIXTURE PRESENT**
   *(Note: Absence of fixtures in the repository does not imply absence of exchange APIs; exchange API availability is classified separately based on official specifications).*
3. **Seeder & Test Fixtures**:
   - `ExchangeSeeder.php`: Seeds master exchange records for `NCDEX` and `MCX`.
   - `MarketBhavcopyServiceTest.php`: Uses in-memory programmatic factory data to test upsert idempotency and cross-exchange injection rejection.

### 2.2 Security Findings (Sanctum `plain_token`)
- **Finding**: Migration `2026_09_05_000001_add_plain_token_to_personal_access_tokens_table.php` added a nullable `plain_token` text column to `personal_access_tokens`.
- **Code Usage**: Auth actions (`LoginAdminAction`, `LoginUserAction`, `RegisterUserAction`, `RefreshTokenAction`) store active plaintext bearer tokens in this column.
- **Classification**: `SECURITY ISSUE`. Storing active plaintext bearer tokens in the database exposes active user and admin sessions to unauthorized compromise if read access to database backups, logs, or replication streams occurs.
- **Remediation Plan**:
  1. Remove `plain_token` column and writes from auth actions.
  2. Implement proper Sanctum token hashing (SHA-256) with client-side token storage.
  3. Support token refreshing using revocable refresh tokens or sliding session expiry rather than plaintext persistence.

---

## 3. Official Source Contracts & Specifications

### 3.1 NCDEX Official Sources

#### A. NCDEX Reference Data Master
- **Source Category**: `MEMBER EXTRANET` / Daily Scheduled Distribution (`common.ncdex.com` / `extranet.ncdex.com`)
- **Format**: Standardized Common Reference Master
- **Key Fields & Token Semantics**:
  - `FinCntrctId`: Instrument TokenID (Numeric string) $\to$ candidate for `exchange_instruments.external_instrument_id`.
  - `FinInstrmId`: Documented as blank for NCDEX in standardized reference master.
  - `TckrSymb`: Product Ticker Symbol (e.g. `CHANA`, `GUARGUM5`, `JEERAUNJHA`) $\to$ `exchange_commodity_mappings.external_symbol`.
  - `FinInstrmNm`: Full instrument descriptive name $\to$ `exchange_instruments.instrument_name`.
  - `MinSprd`: Tick size for instrument $\to$ `exchange_instruments.tick_size`.
  - `MarketLot` / `LotSize`: Contract lot size $\to$ `exchange_instruments.lot_size`.
- **Token Bridge Verification**: `NOT VERIFIABLE` until actual simultaneous Bhavcopy and Master files are compared.

#### B. NCDEX Final UDiFF Bhavcopy Specification (34 Columns)
- **Source Category**: `FILE DOWNLOAD` (SEBI Unified Distributed File Format Mandate)
- **File Pattern**: `BhavCopy_NCD_CO_0_0_0_YYYYMMDD_F_0000.csv`
- **Full 34-Field Header List**:
  1. `TradDt` (Trade Date: YYYY-MM-DD) $\to$ `market_bhavcopies.trade_date`
  2. `BizDt` (Settlement Business Date: YYYY-MM-DD)
  3. `Sgmt` (Segment: `COM`)
  4. `Src` (Source MII: `NCD`)
  5. `FinInstrmTp` (Instrument Type: `COM`, `COF`, `COO`, `FUO`, `IDF`) $\to$ `exchange_instruments.instrument_type`
  6. `FinInstrmId` (Unique Token/Instrument ID: numeric string) $\to$ `exchange_instruments.external_instrument_id`
  7. `ISIN` (Security ISIN)
  8. `TckrSymb` (Product Ticker Symbol, e.g. `CHANA`) $\to$ `exchange_commodity_mappings.external_symbol`
  9. `SctySrs` (Security Series)
  10. `XpryDt` (Original Expiry Date: YYYY-MM-DD) $\to$ `exchange_instruments.original_expiry_date`
  11. `FinInstrmActlXpryDt` (Actual Expiry Date: YYYY-MM-DD) $\to$ `exchange_instruments.actual_expiry_date`
  12. `StrkPric` (Strike Price) $\to$ `exchange_instruments.strike_price`
  13. `OptnTp` (Option Type: `CE`, `PE`, `XX`/blank) $\to$ `exchange_instruments.option_type`
  14. `FinInstrmNm` (Instrument Full Name) $\to$ `exchange_instruments.instrument_name`
  15. `OpnPric` $\to$ `market_bhavcopies.open_price`
  16. `HghPric` $\to$ `market_bhavcopies.high_price`
  17. `LwPric` $\to$ `market_bhavcopies.low_price`
  18. `ClsPric` $\to$ `market_bhavcopies.close_price`
  19. `LastPric` $\to$ `market_bhavcopies.last_price`
  20. `PrvsClsgPric` $\to$ `market_bhavcopies.previous_close_price`
  21. `UndrlygPric` $\to$ Spot reference price
  22. `SttlmPric` $\to$ `market_bhavcopies.settlement_price`
  23. `OpnIntrst` $\to$ `market_bhavcopies.open_interest`
  24. `ChngInOpnIntrst` $\to$ `market_bhavcopies.change_in_open_interest`
  25. `TtlTradgVol` $\to$ `market_bhavcopies.volume`
  26. `TtlTrfVal` $\to$ `market_bhavcopies.traded_value` (Turnover - Raw Decimal preserved until unit confirmed)
  27. `TtlNbOfTxsExctd` $\to$ `market_bhavcopies.number_of_trades`
  28. `SsnId` $\to$ Trading Session ID
  29. `NewBrdLotQty` (Lot Size / Board Lot Quantity) $\to$ `exchange_instruments.lot_size`
  30. `Rmks` (Exchange Remarks)
  31. `Rsvd1` (Reserved Field 1)
  32. `Rsvd2` (Reserved Field 2)
  33. `Rsvd3` (Reserved Field 3)
  34. `Rsvd4` (Reserved Field 4)

---

### 3.2 MCX Official Sources

#### A. MCX Current Reference Master Specification
- **Official Specification**: `MCX Reference Data Files (Masters) Version 1.2` (March 10, 2026)
- **Current Instrument Master File**: `MCXScrips.bcp` (Comma Separated File / BCP format)
- *(Note: Generic filenames like `contract.csv` or `symb_master.csv` are legacy and superseded by `MCXScrips.bcp`).*
- **Exact Current MCX Field Contract**:

| MCX Source Field | Source Type / Format | Source Unit / Scale | Internal Target Field | Transformation / Normalization |
| :--- | :--- | :--- | :--- | :--- |
| **Instrument Identifier** | Integer / Numeric String | Unique Token | `exchange_instruments.external_instrument_id` | Direct String Assignment |
| **Symbol** | String | Ticker Code | `exchange_instruments.symbol` | Direct String Assignment |
| **Instrument Series** | String | Series Tag | (Reference Attribute) | None |
| **Instrument Type** | String (`FUTCUR`, `FUTCOM`, `OPTFUT`) | Standard Code | `exchange_instruments.instrument_type` | Direct String Assignment |
| **ProductID** | Integer | Exchange Product ID | (Reference Attribute) | None |
| **Instrument Start Date** | Integer (Epoch Seconds) | Seconds since 01-01-1970 IST | `exchange_instruments.listed_at` | `Carbon::createFromTimestamp($val, 'Asia/Kolkata')->setTimezone('UTC')` |
| **Last Trading Date** | Integer (Epoch Seconds) | Seconds since 01-01-1970 IST | `exchange_instruments.actual_expiry_date` | Date extraction (`YYYY-MM-DD`) |
| **Lot Size** | Integer / Decimal | Units per Lot | `exchange_instruments.lot_size` | Direct Numeric Assignment |
| **Tick Size** | Integer / Decimal | **Amount in Paise** | `exchange_instruments.tick_size` | **Value divided by 100** ($\text{Paise} \to \text{INR}$) |
| **Instrument Description** | String | Text Description | (Reference Attribute) | None |
| **Instrument Status flag** | String / Char | Status Code | `exchange_instruments.lifecycle_status` | Status mapping (`active`, `expired`, etc.) |
| **Name of Underlying Asset** | String | Commodity Name | `exchange_commodity_mappings.external_symbol` | Lookup mapping key |
| **Identifier of underlying** | Integer / String | Underlying ID | `exchange_commodity_mappings.external_code` | Secondary lookup key |
| **Instrument Name** | String | Contract Name | `exchange_instruments.instrument_name` | Direct String Assignment |
| **Original Expiry Date** | Integer (Epoch Seconds) | Seconds since 01-01-1970 IST | `exchange_instruments.original_expiry_date` | Date extraction (`YYYY-MM-DD`) |
| **Strike price** | Decimal | Price in INR | `exchange_instruments.strike_price` | Direct Decimal Assignment (Scaled if paise) |
| **Option Type** | String (`CA`, `PA`, `CE`, `PE`, `-`) | Option Style + Type | `exchange_instruments.option_type` | `CA`/`CE` $\to$ `'call'`, `PA`/`PE` $\to$ `'put'` |
| **Price quote unit** | String | Unit (e.g. `10 GRMS`, `1 KGS`) | `exchange_instruments.contract_unit` | Direct String Assignment |
| **Price quote quantity** | Decimal | Quantity multiplier | (Scaling Factor) | Used with Numerator/Denominator |
| **Trading unit** | String | Unit (e.g. `KGS`, `MT`) | (Reference Attribute) | None |
| **Trading unit factor** | Decimal | Unit multiplier | (Reference Attribute) | None |
| **ProductName** | String | Product Name | `exchange_commodity_mappings.external_symbol` | Fallback lookup |

#### B. MCX Scaling, Numerator/Denominator & Price Semantics
- **Tick Size**: Specified in **paise**. Must be divided by $100$ before storage in `exchange_instruments.tick_size`.
- **Decimal Locator & Scaling Factors**: `Decimal Locator`, `Price Numerator`, `Price Denominator`, `General Numerator`, and `General Denominator` define the precision placement for raw broadcast/ETI streams. For standard master files, verified values must be checked before applying multipliers to avoid double-scaling.

#### C. MCX Option Types
- Supports: `CA` (Call American), `PA` (Put American), `CE` (Call European), `PE` (Put European).
- Normalization:
  - `CA` $\to$ `'call'`
  - `CE` $\to$ `'call'`
  - `PA` $\to$ `'put'`
  - `PE` $\to$ `'put'`
- Schema Note: The `exchange_instruments.option_type` column is `VARCHAR(20)` nullable, which accommodates both normalized `'call'`/`'put'` and style distinctions without schema modification.

#### D. MCX Daily Bhavcopy & EOD Sources
- **Standard 1 (SEBI UDiFF CSV Mandate)**: `BhavCopy_MCX_CO_0_0_0_YYYYMMDD_F_0000.csv` (machine-readable 34-column format).
- **Standard 2 (MCX Public Web Table Summary)**: Fallback summary requiring $\times 1,000$ for volume and $\times 100,000$ for value.

---

## 4. API & Interface Classification Matrix

| Interface Category | NCDEX Classification | MCX Classification | Role in Vyapari Darbaar |
| :--- | :--- | :--- | :--- |
| **PUBLIC REST JSON** | **None Verified** (No unauthenticated public REST JSON endpoint verified) | **None Verified** (No unauthenticated public REST JSON market endpoint verified) | Not Applicable for direct unauthenticated ingestion |
| **LICENSED / API DELIVERY** | **Documented in Exchange Workflow** (Official NCDEX Data Request workflow includes API delivery) | **Documented in Commercial Market Data** (Direct vendor/member feeds) | Future Phase (requires licensing/auth credentials) |
| **BROADCAST API** | Not used for EOD | **Documented & Operational** (MDI, EMDI, EOBI multicast/socket interfaces) | Real-time tick ingestion (High-frequency live trading, out of scope for EOD Bhavcopy) |
| **FILE DOWNLOAD** | **Standard Mandated Format** (Public UDiFF CSV daily files) | **Standard Mandated Format** (UDiFF CSV & Daily EOD Web summaries) | **Primary EOD Bhavcopy Source** |
| **MEMBER EXTRANET** | **Standard Reference Source** (`ncdex_contract_new_common.csv`) | **Standard Reference Source** (`MCXScrips.bcp` via SFTP / Extranet) | **Primary Instrument Reference Data Source** |

---

## 5. Instrument Identity & Matching Algorithms

### 5.1 NCDEX Instrument Identity
1. **Primary Identity (Deterministic Token Match)**:
   - Match by `exchange_id = NCDEX.id` AND `external_instrument_id = FinInstrmId` (from UDiFF column 6).
   - In reference master, the token is provided in `FinCntrctId`.
   - **Verification Status**: Marked `NOT VERIFIABLE` until real cross-file inspection proves identity alignment.
2. **Deterministic Fallback Identity (When Token is absent or unmatched)**:
   - For **Futures**:
     - `exchange_id = NCDEX.id`
     - `mapping.external_symbol = TckrSymb`
     - `instrument_type = FinInstrmTp`
     - `actual_expiry_date = FinInstrmActlXpryDt`
   - For **Options** (MUST additionally distinguish):
     - `strike_price = StrkPric`
     - `option_type = OptnTp`
   - **NO fuzzy string matching** is permitted.

### 5.2 MCX Instrument Identity
1. **Primary Identity (Deterministic Token Match)**:
   - Match by `exchange_id = MCX.id` AND `external_instrument_id = Instrument Identifier` from `MCXScrips.bcp`.
2. **Deterministic Fallback Identity (For Web Summary Reports without Token)**:
   - For **Futures**:
     - `exchange_id = MCX.id`
     - `mapping.external_symbol = Commodity` / `Name of Underlying Asset`
     - `instrument_type = Instrument` / `Instrument Type`
     - `actual_expiry_date = ParsedExpiryDate`
   - For **Options**:
     - `strike_price = StrikePrice`
     - `option_type = OptionType`

---

## 6. Numerical, Unit & Timestamp Semantics

### 6.1 Lot Size & Tick Size Source Priority
- **Lot Size (`lot_size`)**:
  - `REFERENCE MASTER` (`MarketLot` in NCDEX, `Lot Size` in `MCXScrips.bcp`) is authoritative primary source.
  - `BHAVCOPY` (`NewBrdLotQty` in 34-column NCDEX UDiFF) acts as validation / fallback.
  - Conflicting values between reference master and bhavcopy must be flagged, not silently overwritten.
- **Tick Size (`tick_size`)**:
  - NCDEX: `MinSprd` from Reference Master.
  - MCX: `Tick Size` from `MCXScrips.bcp` (divided by 100 to convert paise to INR).
  - Invariant: If not verified by source, leave `NULL`. Never invent a default.

### 6.2 Units of Volume, OI & Traded Value
- **Traded Value (Turnover)**:
  - NCDEX UDiFF `TtlTrfVal`: Status = `NOT CONFIRMED`. Parser will preserve raw decimal without unverified multiplier until confirmed by actual file inspection.
  - MCX UDiFF `TtlTrfVal`: Absolute INR turnover.
  - MCX Web Display `Value (Lakhs)`: Multiplied by $100,000$ before storage $\to$ `traded_value`.
- **Volume & Open Interest**:
  - UDiFF `TtlTradgVol` / `OpnIntrst`: Contract volume / OI units.
  - MCX Web Display `Volume (000's)`: Multiplied by $1,000$ before storage $\to$ `volume`.
  - MCX Web Display `OI (Lots)`: Represented in lots.

### 6.3 NULL vs ZERO Invariant
- Project invariant strictly preserved:
  - Blank / absent / null / `"-"` / `"NA"` $\to$ `NULL`.
  - Explicit numeric zero (`0`, `"0"`, `"0.00"`) $\to$ `0`.
- No blanket 0-to-NULL conversion for OHLC, LTP, previous close, settlement, volume, traded value, OI, or OI change.

### 6.4 Timestamp & Timezone Semantics
- **Exchange Trade Date**: Indian Standard Time (IST, `Asia/Kolkata`, UTC+05:30). Parsed as `YYYY-MM-DD` date string.
- **MCX Epoch Dates**: Converted from seconds since `01-01-1970 00:00:00` in IST.
- **System Ingestion Timestamps**: Standard UTC datetime (`started_at`, `finished_at`, `created_at`, `updated_at`).

---

## 7. Compatibility Audit: DTO & Database

### 7.1 `NormalizedBhavcopyRowDTO` Compatibility
- **Status**: **COMPATIBLE**.
- The DTO accepts:
  - `externalInstrumentId`, `instrumentId`, `tradeDate`
  - `openPrice`, `highPrice`, `lowPrice`, `closePrice`, `lastPrice`, `previousClosePrice`, `settlementPrice`
  - `volume`, `tradedValue`, `numberOfTrades`, `openInterest`, `changeInOpenInterest`
  - `sourceTimestamp`

### 7.2 Database Tables Compatibility
- **`exchange_commodity_mappings`**: **NO CHANGE REQUIRED**.
- **`exchange_instruments`**: **NO CHANGE REQUIRED**.
- **`market_bhavcopies`**: **NO CHANGE REQUIRED**.
- **`market_ingestion_runs`**: **NO CHANGE REQUIRED**.

---

## 8. Provider Readiness Statuses & Exact Blockers

| Provider / Interface Component | Readiness Status | Classification / Exact Blocker |
| :--- | :--- | :--- |
| **NCDEX EOD Bhavcopy Provider** | **BLOCKED** | Missing actual official production sample fixture file in `tests/Fixtures/MarketData/NCDEX/bhavcopy/`. |
| **NCDEX Reference Data Provider** | **BLOCKED** | Missing actual official reference master file in `tests/Fixtures/MarketData/NCDEX/reference/`. |
| **MCX EOD Bhavcopy Provider** | **BLOCKED** | Missing actual official production sample fixture file in `tests/Fixtures/MarketData/MCX/bhavcopy/`. |
| **MCX Reference Data Provider** | **BLOCKED** | Missing actual official `MCXScrips.bcp` reference master file in `tests/Fixtures/MarketData/MCX/reference/`. |
| **NCDEX API-based Acquisition** | **LICENSED-ONLY / NOT CONFIRMED** | No public unauthenticated REST API; official data request workflow documents API delivery option. |
| **MCX REST JSON Acquisition** | **NOT CONFIRMED** | No public unauthenticated REST JSON market-data endpoint verified. |
| **MCX Broadcast Interface** | **DOCUMENTED** | MDI, EMDI, EOBI multicast broadcast interfaces documented for live market data (separate from EOD). |

---

## 9. Implementation Plan & Order (Post-Unblocking)

```
Acquire Official Sample Fixtures
    ↓
tests/Fixtures/MarketData/ (NCDEX & MCX)
    ↓
Exchange-specific Providers:
  ├── NCDEXBhavcopyProvider (34-column UDiFF)
  ├── MCXBhavcopyProvider (UDiFF & web summary)
  ├── NCDEXReferenceDataProvider
  └── MCXReferenceDataProvider (MCXScrips.bcp)
    ↓
Normalized DTO (NormalizedBhavcopyRowDTO)
    ↓
Generic Market Service (MarketBhavcopyService)
    ↓
MySQL 8.4 InnoDB Tables
```
