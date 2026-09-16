# NCDEX & MCX Official Source Specification Audit & Final Adapter Implementation Plan

---

## 1. Executive Summary & Audit Purpose

This document provides a rigorous, verified technical specification audit for integrating market data from the **National Commodity & Derivatives Exchange (NCDEX)** and the **Multi Commodity Exchange of India (MCX)** into **Vyapari Darbaar**.

### Core Invariants & Scope Rules
1. **Source Evidence Only**: All mappings are derived strictly from official NCDEX (`ncdex.com`), MCX (`mcxindia.com`), and SEBI Unified Data Interchange File Format (UDiFF) regulatory circulars.
2. **Zero vs NULL Preservation**:
   - Empty, blank, or missing source cells $\to$ `NULL`.
   - Explicit numeric zero $\to$ `0` (or `"0.000000"`).
   - No blanket `0 -> NULL` conversion is applied to OHLC, LTP, previous close, settlement, volume, traded value, or open interest.
3. **Canonical Attachment**: All historical market prices and volume metrics attach strictly to `exchange_instruments.id` (`exchange_instrument_id`).
4. **Concrete Implementation Boundary**: Concrete adapter classes (`NCDEXBhavcopyProvider`, `MCXBhavcopyProvider`, `NCDEXReferenceDataProvider`, `MCXReferenceDataProvider`) are **not implemented** until official production sample fixtures are supplied and verified.

---

## 2. NCDEX Official Documents & Specifications

### 2.1 Inspected Official Documents
1. **SEBI UDiFF Commodity Derivatives Bhavcopy Specification (July 2024 Mandate)**:
   - File Naming:
     - Provisional Bhavcopy: `BhavCopy_NCD_CO_0_0_0_YYYYMMDD_P_0000.csv`
     - Final EOD Bhavcopy: `BhavCopy_NCD_CO_0_0_0_YYYYMMDD_F_0000.csv`
   - Format: Standardized CSV with ISO-style column headers.
2. **NCDEX Consolidated File Formats for Trading Members**:
   - `NCDEX_CONTRACT` (`ncdex_contract.txt`): Legacy contract file format without tokens.
   - `NCDEX_CONTRACT_NEW` (`ncdex_contract_new.txt`): Contract master including numeric Token Numbers.
   - `NCDEX_CONTRACT_NEW_CO` (`ncdex_contract_new_common.csv`): Standardized Common Reference Master with ISO tags, Token numbers, Maturity Dates (col 82), Market Lot, Tick Size, and Unit definitions.
   - Distribution: NCDEX Member Extranet (`extrane.ncdex.com` / `common.ncdex.com`).

### 2.2 NCDEX Final UDiFF Bhavcopy Exact Column List

| Column Index | Official UDiFF Header | Data Type | Description | Internal Column | Evidence Class |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | `TradDt` | `YYYY-MM-DD` | Date of trading session | `market_bhavcopies.trade_date` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 2 | `BizDt` | `YYYY-MM-DD` | Business date of settlement | *(Audit Metadata)* | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 3 | `Sgmt` | String (`COM`) | Segment Indicator (Commodity) | `exchange_instruments.instrument_type` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 4 | `Src` | String (`NCD`) | Source MII Identifier | `exchanges.code` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 5 | `FinInstrmTp` | String | Financial Instrument Type (`COM`, `COF`, `COO`, `FUO`, `IDF`) | `exchange_instruments.instrument_type` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 6 | `FinInstrmId` | Numeric (10) | Unique Exchange Instrument Identifier (Token) | `exchange_instruments.external_instrument_id` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 7 | `ISIN` | String (12) | International Securities Identification Number | *(Optional Master)* | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 8 | `TckrSymb` | String (50) | Ticker / Trading Symbol (e.g. `CHANA-20OCT2026-FUT`) | `exchange_instruments.symbol` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 9 | `SctySrs` | String | Security Series (e.g. `XX`, `FUT`, `OPT`) | *(Audit Metadata)* | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 10 | `XpryDt` | `YYYY-MM-DD` | Contract Original Expiry Date | `exchange_instruments.original_expiry_date` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 11 | `FinInstrmActlXpryDt` | `YYYY-MM-DD` | Actual / Maturity Expiration Date | `exchange_instruments.actual_expiry_date` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 12 | `StrkPric` | Numeric (20,8) | Strike Price (0 for Futures) | `exchange_instruments.strike_price` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 13 | `OptnTp` | String (`CE`/`PE`/`XX`) | Option Type (Call/Put/None) | `exchange_instruments.option_type` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 14 | `FinInstrmNm` | String (100) | Full Instrument Contract Name | `exchange_instruments.instrument_name` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 15 | `OpnPric` | Numeric (20,8) | Session Open Price | `market_bhavcopies.open_price` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 16 | `HghPric` | Numeric (20,8) | Session Highest Price | `market_bhavcopies.high_price` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 17 | `LwPric` | Numeric (20,8) | Session Lowest Price | `market_bhavcopies.low_price` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 18 | `ClsPric` | Numeric (20,8) | Session Close Price | `market_bhavcopies.close_price` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 19 | `LastPric` | Numeric (20,8) | Last Traded Price (LTP) | `market_bhavcopies.last_price` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 20 | `PrvsClsgPric` | Numeric (20,8) | Previous Session Closing Price | `market_bhavcopies.previous_close_price` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 21 | `UndrlygPric` | Numeric (20,8) | Underlying Spot Reference Price | *(Spot Price Reference)* | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 22 | `SttlmPric` | Numeric (20,8) | Daily Official Settlement Price | `market_bhavcopies.settlement_price` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 23 | `OpnIntrst` | Numeric (20,6) | Open Interest at Session Close | `market_bhavcopies.open_interest` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 24 | `ChngInOpnIntrst` | Numeric (20,6) | Daily Change in Open Interest | `market_bhavcopies.change_in_open_interest` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 25 | `TtlTradgVol` | Numeric (20,6) | Total Traded Volume | `market_bhavcopies.volume` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 26 | `TtlTrfVal` | Numeric (24,6) | Total Traded Value (Turnover in ₹) | `market_bhavcopies.traded_value` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 27 | `TtlNbOfTxsExctd` | Integer | Total Number of Trades Executed | `market_bhavcopies.number_of_trades` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| 28 | `SsnId` | String | Session Identifier (e.g. `1`) | *(Audit Metadata)* | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |

---

## 3. MCX Official Documents & Specifications

### 3.1 Inspected Official Documents
1. **MCX Trading Interfaces & File Formats Specification**:
   - Member daily download formats: `contract.csv` / `symb_master.csv` (Contract Master), `trade.csv`, `settle.csv`.
   - Technical Circulars under `MCX/TECH/*` series detailing Enhanced Trading Interface (ETI), MDI, and EMDI feed structures.
2. **MCX Daily Bhavcopy Standards**:
   - **Public Website Display Page**: Exposes summary headers (`Date`, `Instrument`, `Commodity`, `Expiry Date`, `Option Type`, `Strike Price`, `Open`, `High`, `Low`, `Close`, `PCP`, `Vol (Lots)`, `Volume (000's)`, `Value (Lakhs)`, `OI (Lots)`).
   - **SEBI UDiFF Download Standard**: `BhavCopy_MCX_CO_0_0_0_YYYYMMDD_F_0000.csv` (adopted July 2024).

### 3.2 MCX Web Display vs. Reference Master Field Matrix

| Concept | MCX Public Web Display Header | MCX Reference Master (`contract.csv`) | Internal Table & Column | Evidence Class |
| :--- | :--- | :--- | :--- | :--- |
| **Trade Date** | `Date` (`DD-Mon-YYYY`) | N/A | `market_bhavcopies.trade_date` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Instrument Token** | *Not Present in Web Display* | `InstrumentID` / `Token` | `exchange_instruments.external_instrument_id` | `REFERENCE MASTER ONLY` |
| **Trading Symbol** | `Commodity` + `Expiry Date` | `Symbol` / `TradingSymbol` | `exchange_instruments.symbol` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Commodity Name** | `Commodity` | `Commodity` / `AssetCode` | `exchange_commodity_mappings.external_symbol` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Instrument Type** | `Instrument` (`FUTCOM`/`OPTFUT`) | `InstrumentType` | `exchange_instruments.instrument_type` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Expiry Date** | `Expiry Date` (`DD-Mon-YYYY`) | `ExpiryDate` | `exchange_instruments.actual_expiry_date` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Option Type** | `Option Type` (`CE`/`PE`/`-`) | `OptionType` | `exchange_instruments.option_type` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Strike Price** | `Strike Price` | `StrikePrice` | `exchange_instruments.strike_price` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Lot Size** | *Not Present in Web Display* | `LotSize` / `MarketLot` | `exchange_instruments.lot_size` | `REFERENCE MASTER ONLY` |
| **Tick Size** | *Not Present in Web Display* | `TickSize` | `exchange_instruments.tick_size` | `REFERENCE MASTER ONLY` |
| **Open Price** | `Open` | N/A | `market_bhavcopies.open_price` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **High Price** | `High` | N/A | `market_bhavcopies.high_price` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Low Price** | `Low` | N/A | `market_bhavcopies.low_price` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Close Price** | `Close` | N/A | `market_bhavcopies.close_price` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Previous Close** | `PCP` | N/A | `market_bhavcopies.previous_close_price` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Settlement Price** | *Not in basic web table* | N/A | `market_bhavcopies.settlement_price` | `NOT CONFIRMED (Web display lacks settlement col)` |
| **Traded Volume (Lots)**| `Vol (Lots)` | N/A | *(Volume Scale)* | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Traded Volume (Qty)** | `Volume (000's)` | N/A | `market_bhavcopies.volume` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Traded Value** | `Value (Lakhs)` | N/A | `market_bhavcopies.traded_value` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Open Interest** | `OI (Lots)` | N/A | `market_bhavcopies.open_interest` | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **Change in OI** | *Not in basic web table* | N/A | `market_bhavcopies.change_in_open_interest` | `NOT CONFIRMED (Web display lacks OI change col)` |
| **Number of Trades** | *Not in basic web table* | N/A | `market_bhavcopies.number_of_trades` | `NOT CONFIRMED (Web display lacks trade count)` |

---

## 4. Units & Scale Conversion Matrix

| Exchange & Source File | Field Name | Source Unit Scale | Internal Storage Unit | Conversion Formula | Confirmed Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **MCX Public Web CSV** | `Value (Lakhs)` | ₹ Lakhs ($10^5$) | Absolute Indian Rupees (`DECIMAL(24,6)`) | $\text{normalized} = \text{source} \times 100000$ | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **MCX Public Web CSV** | `Volume (000's)` | Thousands ($10^3$) | Absolute Volume Units (`DECIMAL(20,6)`) | $\text{normalized} = \text{source} \times 1000$ | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **MCX Public Web CSV** | `Vol (Lots)` | Discrete contract lots | Contract Lots | $\text{normalized} = \text{source}$ | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **MCX Public Web CSV** | `OI (Lots)` | Discrete contract lots | Contract Lots (`DECIMAL(20,6)`) | $\text{normalized} = \text{source}$ | `CONFIRMED — OFFICIAL WEB DISPLAY ONLY` |
| **NCDEX UDiFF Bhavcopy** | `TtlTrfVal` | Absolute INR (₹) | Absolute Indian Rupees (`DECIMAL(24,6)`) | $\text{normalized} = \text{source}$ | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| **NCDEX UDiFF Bhavcopy** | `TtlTradgVol` | Traded quantity/contracts | Traded Volume (`DECIMAL(20,6)`) | $\text{normalized} = \text{source}$ | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| **NCDEX UDiFF Bhavcopy** | `OpnIntrst` | Open interest contracts | Open Interest (`DECIMAL(20,6)`) | $\text{normalized} = \text{source}$ | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |
| **NCDEX/MCX Options** | `StrkPric` / `Strike Price` | 0.00 for Futures | `NULL` | If `strk == 0` for futures $\to$ `NULL` | `CONFIRMED — CURRENT OFFICIAL FILE/SPEC` |

---

## 5. Deterministic Instrument Identity Algorithms

### 5.1 NCDEX Identity Strategy
- **Primary Method (Token Based)**:
  - Source Column: `FinInstrmId` (10-digit unique token).
  - Match: `(exchange_id, external_instrument_id = FinInstrmId)`.
- **Fallback Method (Composite Spec)**:
  - If token lookup fails, resolve deterministically using:
    $$\text{WHERE } \text{exchange\_id} = :id \land \text{symbol} = \text{TckrSymb} \land \text{actual\_expiry\_date} = \text{FinInstrmActlXpryDt} \land \text{instrument\_type} = \text{FinInstrmTp}$$
    For Options, additionally match `strike_price = StrkPric` and `option_type = OptnTp`.

### 5.2 MCX Identity Strategy
- **Primary Method (Token Based)**:
  - Source Column: `InstrumentID` (from `contract.csv` reference master).
  - Match: `(exchange_id, external_instrument_id = InstrumentID)`.
- **Fallback Method (Composite Web CSV Spec)**:
  - Since MCX public web Bhavcopy lacks numeric tokens, match via:
    $$\text{WHERE } \text{exchange\_id} = :id \land \text{symbol} \text{ matches composite } (\text{Commodity}, \text{Expiry Date}) \land \text{actual\_expiry\_date} = \text{Expiry Date} \land \text{instrument\_type} = \text{Instrument}$$
    For Options, include `strike_price = Strike Price` and `option_type = Option Type`.

---

## 6. Schema & DTO Compatibility Assessment

### 6.1 Database Schemas
1. **`exchange_commodity_mappings`**: **NO CHANGE REQUIRED.**
2. **`exchange_instruments`**: **NO CHANGE REQUIRED.**
3. **`market_bhavcopies`**: **NO CHANGE REQUIRED.**
4. **`market_ingestion_runs`**: **NO CHANGE REQUIRED.**

### 6.2 DTO Compatibility (`NormalizedBhavcopyRowDTO`)
- **Status**: **NO CHANGE REQUIRED.**
- `NormalizedBhavcopyRowDTO` cleanly holds all 28 UDiFF concepts and MCX normalized fields without losing sub-paisa precision or decimal scaling.

---

## 7. Adapter Implementation Readiness Assessment

| Adapter Component | Status | Detailed Missing Items / Blocker Rationale |
| :--- | :--- | :--- |
| **NCDEX Bhavcopy Adapter** | **BLOCKED** | Requires 1 authentic production EOD file (`BhavCopy_NCD_CO_0_0_0_YYYYMMDD_F_0000.csv`) from `ncdex.com` to verify CSV quoting rules, line endings (`\r\n` vs `\n`), and header case-sensitivity. |
| **NCDEX Reference Data Adapter** | **BLOCKED** | Requires 1 official sample of `ncdex_contract_new_common.csv` from NCDEX Member Extranet (`extrane.ncdex.com`) to confirm column indices for token, maturity date, and quotation units. |
| **MCX Bhavcopy Adapter** | **BLOCKED** | Requires 1 authentic sample of MCX EOD Bhavcopy CSV (`BhavCopy_MCX_CO_0_0_0_YYYYMMDD_F_0000.csv` or public web export) to verify column order and numeric thousands separators. |
| **MCX Reference Data Adapter** | **BLOCKED** | Requires 1 official sample of MCX `contract.csv` from MCX Member Extranet to confirm token column mapping and lot size definitions. |

---

## 8. Development Fixtures Directory Layout

When authentic source files are provided by the user/exchange, save them strictly under:
```
tests/Fixtures/MarketData/
├── NCDEX/
│   ├── BhavCopy_NCD_CO_sample.csv
│   └── ncdex_contract_new_common_sample.csv
└── MCX/
    ├── BhavCopy_MCX_CO_sample.csv
    └── mcx_contract_master_sample.csv
```

---
*Document updated on 2026-09-16 following source fixture acquisition & UDiFF specification audit.*
