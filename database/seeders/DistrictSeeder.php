<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Complete District Master Data across Indian States & Union Territories.
     */
    public function run(): void
    {
        $statesMap = State::all()->keyBy('code');

        $stateDistricts = [
            // Maharashtra (MH)
            'MH' => [
                ['name' => 'Nagpur', 'code' => 'NGP'],
                ['name' => 'Akola', 'code' => 'AKL'],
                ['name' => 'Latur', 'code' => 'LTR'],
                ['name' => 'Nashik', 'code' => 'NSK'],
                ['name' => 'Pune', 'code' => 'PUN'],
                ['name' => 'Ahmednagar', 'code' => 'AHM'],
                ['name' => 'Amravati', 'code' => 'AMR'],
                ['name' => 'Jalgaon', 'code' => 'JAL'],
                ['name' => 'Chhatrapati Sambhajinagar', 'code' => 'CSN'],
                ['name' => 'Nanded', 'code' => 'NAN'],
                ['name' => 'Kolhapur', 'code' => 'KLP'],
                ['name' => 'Solapur', 'code' => 'SLP'],
                ['name' => 'Sangli', 'code' => 'SNG'],
                ['name' => 'Satara', 'code' => 'SAT'],
                ['name' => 'Yavatmal', 'code' => 'YAV'],
                ['name' => 'Wardha', 'code' => 'WRD'],
                ['name' => 'Buldhana', 'code' => 'BLD'],
                ['name' => 'Washim', 'code' => 'WAS'],
                ['name' => 'Hingoli', 'code' => 'HNG'],
                ['name' => 'Parbhani', 'code' => 'PRB'],
                ['name' => 'Beed', 'code' => 'BED'],
                ['name' => 'Jalna', 'code' => 'JLN'],
                ['name' => 'Dharashiv', 'code' => 'DHR'],
                ['name' => 'Chandrapur', 'code' => 'CHP'],
                ['name' => 'Bhandara', 'code' => 'BND'],
                ['name' => 'Gondia', 'code' => 'GND'],
                ['name' => 'Gadchiroli', 'code' => 'GDC'],
                ['name' => 'Dhule', 'code' => 'DHL'],
                ['name' => 'Nandurbar', 'code' => 'NDB'],
                ['name' => 'Thane', 'code' => 'THN'],
                ['name' => 'Palghar', 'code' => 'PLG'],
                ['name' => 'Raigad', 'code' => 'RGD'],
                ['name' => 'Ratnagiri', 'code' => 'RTG'],
                ['name' => 'Sindhudurg', 'code' => 'SND'],
                ['name' => 'Mumbai City', 'code' => 'MUM'],
                ['name' => 'Mumbai Suburban', 'code' => 'MSU'],
            ],

            // Madhya Pradesh (MP)
            'MP' => [
                ['name' => 'Indore', 'code' => 'IND'],
                ['name' => 'Ujjain', 'code' => 'UJN'],
                ['name' => 'Neemuch', 'code' => 'NMH'],
                ['name' => 'Mandsaur', 'code' => 'MDR'],
                ['name' => 'Bhopal', 'code' => 'BPL'],
                ['name' => 'Gwalior', 'code' => 'GWL'],
                ['name' => 'Jabalpur', 'code' => 'JBL'],
                ['name' => 'Sagar', 'code' => 'SGR'],
                ['name' => 'Dewas', 'code' => 'DWS'],
                ['name' => 'Ratlam', 'code' => 'RTL'],
                ['name' => 'Khargone', 'code' => 'KRG'],
                ['name' => 'Khandwa', 'code' => 'KND'],
                ['name' => 'Dhar', 'code' => 'DHR'],
                ['name' => 'Harda', 'code' => 'HRD'],
                ['name' => 'Narmadapuram', 'code' => 'NMD'],
                ['name' => 'Sehore', 'code' => 'SHR'],
                ['name' => 'Vidisha', 'code' => 'VDS'],
                ['name' => 'Raisen', 'code' => 'RSN'],
                ['name' => 'Betul', 'code' => 'BTL'],
                ['name' => 'Chhindwara', 'code' => 'CHW'],
                ['name' => 'Narsinghpur', 'code' => 'NSP'],
                ['name' => 'Damoh', 'code' => 'DMH'],
                ['name' => 'Rewa', 'code' => 'REW'],
                ['name' => 'Satna', 'code' => 'STN'],
                ['name' => 'Katni', 'code' => 'KTN'],
                ['name' => 'Guna', 'code' => 'GNA'],
                ['name' => 'Shivpuri', 'code' => 'SVP'],
                ['name' => 'Morena', 'code' => 'MRN'],
                ['name' => 'Bhind', 'code' => 'BHD'],
                ['name' => 'Sheopur', 'code' => 'SHP'],
                ['name' => 'Shajapur', 'code' => 'SJP'],
                ['name' => 'Agar Malwa', 'code' => 'AGM'],
                ['name' => 'Barwani', 'code' => 'BRW'],
                ['name' => 'Burhanpur', 'code' => 'BHP'],
                ['name' => 'Alirajpur', 'code' => 'ALR'],
                ['name' => 'Jhabua', 'code' => 'JHB'],
                ['name' => 'Chhatarpur', 'code' => 'CHT'],
                ['name' => 'Tikamgarh', 'code' => 'TKM'],
                ['name' => 'Panna', 'code' => 'PNA'],
                ['name' => 'Seoni', 'code' => 'SNI'],
                ['name' => 'Balaghat', 'code' => 'BLG'],
                ['name' => 'Mandla', 'code' => 'MDL'],
                ['name' => 'Dindori', 'code' => 'DND'],
                ['name' => 'Shahdol', 'code' => 'SHD'],
                ['name' => 'Umaria', 'code' => 'UMR'],
                ['name' => 'Anuppur', 'code' => 'ANP'],
                ['name' => 'Sidhi', 'code' => 'SDH'],
                ['name' => 'Singrauli', 'code' => 'SGL'],
            ],

            // Gujarat (GJ)
            'GJ' => [
                ['name' => 'Rajkot', 'code' => 'RJT'],
                ['name' => 'Patan', 'code' => 'PTN'],
                ['name' => 'Ahmedabad', 'code' => 'AMD'],
                ['name' => 'Surat', 'code' => 'SRT'],
                ['name' => 'Vadodara', 'code' => 'VAD'],
                ['name' => 'Bhavnagar', 'code' => 'BHV'],
                ['name' => 'Jamnagar', 'code' => 'JAM'],
                ['name' => 'Junagadh', 'code' => 'JND'],
                ['name' => 'Amreli', 'code' => 'AMR'],
                ['name' => 'Banaskantha', 'code' => 'BNK'],
                ['name' => 'Sabarkantha', 'code' => 'SBK'],
                ['name' => 'Mehsana', 'code' => 'MSN'],
                ['name' => 'Gandhinagar', 'code' => 'GNR'],
                ['name' => 'Anand', 'code' => 'AND'],
                ['name' => 'Kheda', 'code' => 'KHD'],
                ['name' => 'Bharuch', 'code' => 'BRC'],
                ['name' => 'Navsari', 'code' => 'NVS'],
                ['name' => 'Valsad', 'code' => 'VLS'],
                ['name' => 'Porbandar', 'code' => 'PBD'],
                ['name' => 'Morbi', 'code' => 'MRB'],
                ['name' => 'Surendranagar', 'code' => 'SRN'],
                ['name' => 'Kutch', 'code' => 'KTC'],
                ['name' => 'Dahod', 'code' => 'DHD'],
                ['name' => 'Panchmahal', 'code' => 'PNM'],
                ['name' => 'Aravalli', 'code' => 'ARV'],
                ['name' => 'Mahisagar', 'code' => 'MHS'],
                ['name' => 'Chhota Udaipur', 'code' => 'CHU'],
                ['name' => 'Narmada', 'code' => 'NRM'],
                ['name' => 'Tapi', 'code' => 'TAP'],
                ['name' => 'Dang', 'code' => 'DNG'],
                ['name' => 'Devbhumi Dwarka', 'code' => 'DBD'],
                ['name' => 'Gir Somnath', 'code' => 'GSM'],
                ['name' => 'Botad', 'code' => 'BTD'],
            ],

            // Rajasthan (RJ)
            'RJ' => [
                ['name' => 'Kota', 'code' => 'KTA'],
                ['name' => 'Jaipur', 'code' => 'JPR'],
                ['name' => 'Jodhpur', 'code' => 'JDH'],
                ['name' => 'Bikaner', 'code' => 'BKN'],
                ['name' => 'Sri Ganganagar', 'code' => 'SGN'],
                ['name' => 'Hanumangarh', 'code' => 'HNM'],
                ['name' => 'Alwar', 'code' => 'ALW'],
                ['name' => 'Bharatpur', 'code' => 'BHT'],
                ['name' => 'Ajmer', 'code' => 'AJM'],
                ['name' => 'Bhilwara', 'code' => 'BLW'],
                ['name' => 'Udaipur', 'code' => 'UDP'],
                ['name' => 'Nagaur', 'code' => 'NGR'],
                ['name' => 'Pali', 'code' => 'PLI'],
                ['name' => 'Baran', 'code' => 'BRN'],
                ['name' => 'Bundi', 'code' => 'BND'],
                ['name' => 'Jhalawar', 'code' => 'JHL'],
                ['name' => 'Sikar', 'code' => 'SKR'],
                ['name' => 'Jhunjhunu', 'code' => 'JJN'],
                ['name' => 'Churu', 'code' => 'CHR'],
                ['name' => 'Tonk', 'code' => 'TNK'],
                ['name' => 'Barmer', 'code' => 'BRM'],
                ['name' => 'Jaisalmer', 'code' => 'JSL'],
                ['name' => 'Jalore', 'code' => 'JLR'],
                ['name' => 'Sirohi', 'code' => 'SRH'],
                ['name' => 'Chittorgarh', 'code' => 'CTG'],
                ['name' => 'Rajsamand', 'code' => 'RSM'],
                ['name' => 'Dungarpur', 'code' => 'DGP'],
                ['name' => 'Banswara', 'code' => 'BSW'],
                ['name' => 'Pratapgarh', 'code' => 'PTG'],
                ['name' => 'Dausa', 'code' => 'DSA'],
                ['name' => 'Sawai Madhopur', 'code' => 'SWM'],
                ['name' => 'Karauli', 'code' => 'KRL'],
                ['name' => 'Dholpur', 'code' => 'DLP'],
            ],

            // Uttar Pradesh (UP)
            'UP' => [
                ['name' => 'Kanpur Nagar', 'code' => 'KNP'],
                ['name' => 'Lucknow', 'code' => 'LKO'],
                ['name' => 'Varanasi', 'code' => 'VNS'],
                ['name' => 'Agra', 'code' => 'AGR'],
                ['name' => 'Prayagraj', 'code' => 'PRY'],
                ['name' => 'Bareilly', 'code' => 'BRL'],
                ['name' => 'Aligarh', 'code' => 'ALG'],
                ['name' => 'Moradabad', 'code' => 'MRD'],
                ['name' => 'Saharanpur', 'code' => 'SHP'],
                ['name' => 'Meerut', 'code' => 'MRT'],
                ['name' => 'Muzaffarnagar', 'code' => 'MZF'],
                ['name' => 'Mathura', 'code' => 'MTR'],
                ['name' => 'Hathras', 'code' => 'HTR'],
                ['name' => 'Mainpuri', 'code' => 'MNP'],
                ['name' => 'Etawah', 'code' => 'ETW'],
                ['name' => 'Jhansi', 'code' => 'JHS'],
                ['name' => 'Banda', 'code' => 'BND'],
                ['name' => 'Hardoi', 'code' => 'HRD'],
                ['name' => 'Lakhimpur Kheri', 'code' => 'LKP'],
                ['name' => 'Sitapur', 'code' => 'STP'],
                ['name' => 'Barabanki', 'code' => 'BBK'],
                ['name' => 'Ayodhya', 'code' => 'AYD'],
                ['name' => 'Gorakhpur', 'code' => 'GKP'],
                ['name' => 'Basti', 'code' => 'BST'],
                ['name' => 'Deoria', 'code' => 'DRO'],
                ['name' => 'Azamgarh', 'code' => 'AZM'],
                ['name' => 'Jaunpur', 'code' => 'JNP'],
                ['name' => 'Mirzapur', 'code' => 'MZP'],
                ['name' => 'Shahjahanpur', 'code' => 'SJP'],
                ['name' => 'Pilibhit', 'code' => 'PLB'],
                ['name' => 'Rampur', 'code' => 'RMP'],
                ['name' => 'Bijnor', 'code' => 'BJN'],
                ['name' => 'Bulandshahr', 'code' => 'BLS'],
                ['name' => 'Hapur', 'code' => 'HPR'],
                ['name' => 'Sambhal', 'code' => 'SMB'],
                ['name' => 'Amroha', 'code' => 'AMR'],
                ['name' => 'Shamli', 'code' => 'SML'],
                ['name' => 'Baghpat', 'code' => 'BGP'],
                ['name' => 'Gautam Buddha Nagar', 'code' => 'GBN'],
                ['name' => 'Ghaziabad', 'code' => 'GZB'],
            ],

            // Punjab (PB)
            'PB' => [
                ['name' => 'Ludhiana', 'code' => 'LDH'],
                ['name' => 'Amritsar', 'code' => 'ASR'],
                ['name' => 'Jalandhar', 'code' => 'JAL'],
                ['name' => 'Patiala', 'code' => 'PTL'],
                ['name' => 'Bathinda', 'code' => 'BTI'],
                ['name' => 'Sangrur', 'code' => 'SNG'],
                ['name' => 'Firozpur', 'code' => 'FZP'],
                ['name' => 'Fazilka', 'code' => 'FZK'],
                ['name' => 'Sri Muktsar Sahib', 'code' => 'MKS'],
                ['name' => 'Moga', 'code' => 'MOG'],
                ['name' => 'Mansa', 'code' => 'MNS'],
                ['name' => 'Faridkot', 'code' => 'FDK'],
                ['name' => 'Barnala', 'code' => 'BNL'],
                ['name' => 'Kapurthala', 'code' => 'KPT'],
                ['name' => 'Hoshiarpur', 'code' => 'HSP'],
                ['name' => 'Gurdaspur', 'code' => 'GDP'],
                ['name' => 'Tarn Taran', 'code' => 'TNT'],
                ['name' => 'Fatehgarh Sahib', 'code' => 'FGS'],
                ['name' => 'Rupnagar', 'code' => 'RUP'],
                ['name' => 'SAS Nagar (Mohali)', 'code' => 'MHL'],
                ['name' => 'Pathankot', 'code' => 'PTK'],
                ['name' => 'Malerkotla', 'code' => 'MLK'],
            ],

            // Haryana (HR)
            'HR' => [
                ['name' => 'Karnal', 'code' => 'KRN'],
                ['name' => 'Sirsa', 'code' => 'SRS'],
                ['name' => 'Fatehabad', 'code' => 'FTB'],
                ['name' => 'Hisar', 'code' => 'HSR'],
                ['name' => 'Jind', 'code' => 'JND'],
                ['name' => 'Kaithal', 'code' => 'KTL'],
                ['name' => 'Kurukshetra', 'code' => 'KRK'],
                ['name' => 'Ambala', 'code' => 'AMB'],
                ['name' => 'Yamunanagar', 'code' => 'YNR'],
                ['name' => 'Panipat', 'code' => 'PNP'],
                ['name' => 'Sonipat', 'code' => 'SNP'],
                ['name' => 'Rohtak', 'code' => 'ROH'],
                ['name' => 'Jhajjar', 'code' => 'JHJ'],
                ['name' => 'Rewari', 'code' => 'REW'],
                ['name' => 'Bhiwani', 'code' => 'BHW'],
                ['name' => 'Charkhi Dadri', 'code' => 'CKD'],
                ['name' => 'Mahendragarh', 'code' => 'MHG'],
                ['name' => 'Palwal', 'code' => 'PLW'],
                ['name' => 'Nuh', 'code' => 'NUH'],
                ['name' => 'Faridabad', 'code' => 'FDB'],
                ['name' => 'Gurugram', 'code' => 'GGM'],
                ['name' => 'Panchkula', 'code' => 'PKL'],
            ],

            // Karnataka (KA)
            'KA' => [
                ['name' => 'Bengaluru Urban', 'code' => 'BLR'],
                ['name' => 'Bengaluru Rural', 'code' => 'BRR'],
                ['name' => 'Belagavi', 'code' => 'BLG'],
                ['name' => 'Bagalkot', 'code' => 'BGK'],
                ['name' => 'Vijayapura', 'code' => 'BJP'],
                ['name' => 'Kalaburagi', 'code' => 'KLB'],
                ['name' => 'Bidar', 'code' => 'BDR'],
                ['name' => 'Raichur', 'code' => 'RCR'],
                ['name' => 'Koppal', 'code' => 'KPL'],
                ['name' => 'Gadag', 'code' => 'GDG'],
                ['name' => 'Dharwad', 'code' => 'DHW'],
                ['name' => 'Uttara Kannada', 'code' => 'UKN'],
                ['name' => 'Haveri', 'code' => 'HVR'],
                ['name' => 'Ballari', 'code' => 'BLR'],
                ['name' => 'Vijayanagara', 'code' => 'VJN'],
                ['name' => 'Chitradurga', 'code' => 'CTA'],
                ['name' => 'Davanagere', 'code' => 'DVG'],
                ['name' => 'Shivamogga', 'code' => 'SMG'],
                ['name' => 'Udupi', 'code' => 'UDP'],
                ['name' => 'Chikkamagaluru', 'code' => 'CKM'],
                ['name' => 'Tumakuru', 'code' => 'TMK'],
                ['name' => 'Kolar', 'code' => 'KLR'],
                ['name' => 'Chikkaballapura', 'code' => 'CBP'],
                ['name' => 'Mandya', 'code' => 'MDY'],
                ['name' => 'Hassan', 'code' => 'HSN'],
                ['name' => 'Dakshina Kannada', 'code' => 'DKN'],
                ['name' => 'Kodagu', 'code' => 'KDG'],
                ['name' => 'Mysuru', 'code' => 'MYS'],
                ['name' => 'Chamarajanagar', 'code' => 'CRN'],
                ['name' => 'Yadgir', 'code' => 'YDG'],
            ],

            // Tamil Nadu (TN)
            'TN' => [
                ['name' => 'Chennai', 'code' => 'CHN'],
                ['name' => 'Coimbatore', 'code' => 'CBE'],
                ['name' => 'Madurai', 'code' => 'MDU'],
                ['name' => 'Tiruchirappalli', 'code' => 'TRY'],
                ['name' => 'Salem', 'code' => 'SLM'],
                ['name' => 'Erode', 'code' => 'ERD'],
                ['name' => 'Tiruppur', 'code' => 'TPR'],
                ['name' => 'Dindigul', 'code' => 'DGL'],
                ['name' => 'Thanjavur', 'code' => 'TNJ'],
                ['name' => 'Cuddalore', 'code' => 'CDL'],
                ['name' => 'Vellore', 'code' => 'VEL'],
                ['name' => 'Tiruvannamalai', 'code' => 'TVM'],
                ['name' => 'Dharmapuri', 'code' => 'DMP'],
                ['name' => 'Krishnagiri', 'code' => 'KGI'],
                ['name' => 'Namakkal', 'code' => 'NMK'],
                ['name' => 'Karur', 'code' => 'KRR'],
                ['name' => 'Perambalur', 'code' => 'PMB'],
                ['name' => 'Ariyalur', 'code' => 'AYR'],
                ['name' => 'Nagapattinam', 'code' => 'NGP'],
                ['name' => 'Tiruvarur', 'code' => 'TVR'],
                ['name' => 'Pudukkottai', 'code' => 'PDK'],
                ['name' => 'Sivaganga', 'code' => 'SVG'],
                ['name' => 'Ramanathapuram', 'code' => 'RMN'],
                ['name' => 'Virudhunagar', 'code' => 'VDN'],
                ['name' => 'Theni', 'code' => 'THN'],
                ['name' => 'Tenkasi', 'code' => 'TSI'],
                ['name' => 'Tirunelveli', 'code' => 'TNV'],
                ['name' => 'Thoothukudi', 'code' => 'TKD'],
                ['name' => 'Kanyakumari', 'code' => 'KNK'],
                ['name' => 'Nilgiris', 'code' => 'NLG'],
            ],

            // Andhra Pradesh (AP)
            'AP' => [
                ['name' => 'Guntur', 'code' => 'GNT'],
                ['name' => 'NTR (Vijayawada)', 'code' => 'NTR'],
                ['name' => 'Krishna', 'code' => 'KRS'],
                ['name' => 'East Godavari', 'code' => 'EGD'],
                ['name' => 'Kakinada', 'code' => 'KKD'],
                ['name' => 'West Godavari', 'code' => 'WGD'],
                ['name' => 'Eluru', 'code' => 'ELR'],
                ['name' => 'Visakhapatnam', 'code' => 'VSK'],
                ['name' => 'Anakapalli', 'code' => 'AKP'],
                ['name' => 'Vizianagaram', 'code' => 'VZM'],
                ['name' => 'Srikakulam', 'code' => 'SKM'],
                ['name' => 'Kurnool', 'code' => 'KNL'],
                ['name' => 'Nandyal', 'code' => 'NDL'],
                ['name' => 'Anantapur', 'code' => 'ATP'],
                ['name' => 'Sri Sathya Sai', 'code' => 'SSS'],
                ['name' => 'YSR Kadapa', 'code' => 'YSR'],
                ['name' => 'Chittoor', 'code' => 'CTR'],
                ['name' => 'Tirupati', 'code' => 'TPT'],
                ['name' => 'SPS Nellore', 'code' => 'NLR'],
                ['name' => 'Prakasam', 'code' => 'PRK'],
            ],

            // Telangana (TS)
            'TS' => [
                ['name' => 'Hyderabad', 'code' => 'HYD'],
                ['name' => 'Ranga Reddy', 'code' => 'RRD'],
                ['name' => 'Medchal-Malkajgiri', 'code' => 'MDM'],
                ['name' => 'Nizamabad', 'code' => 'NZB'],
                ['name' => 'Warangal', 'code' => 'WGL'],
                ['name' => 'Hanamkonda', 'code' => 'HNK'],
                ['name' => 'Karimnagar', 'code' => 'KRM'],
                ['name' => 'Khammam', 'code' => 'KMM'],
                ['name' => 'Nalgonda', 'code' => 'NLG'],
                ['name' => 'Mahabubnagar', 'code' => 'MBN'],
                ['name' => 'Adilabad', 'code' => 'ADB'],
                ['name' => 'Jagtial', 'code' => 'JGL'],
                ['name' => 'Kamareddy', 'code' => 'KMR'],
                ['name' => 'Sangareddy', 'code' => 'SRD'],
                ['name' => 'Siddipet', 'code' => 'SDP'],
                ['name' => 'Suryapet', 'code' => 'SYP'],
                ['name' => 'Yadadri Bhuvanagiri', 'code' => 'YDB'],
            ],

            // Bihar (BR)
            'BR' => [
                ['name' => 'Patna', 'code' => 'PAT'],
                ['name' => 'Muzaffarpur', 'code' => 'MUZ'],
                ['name' => 'Gaya', 'code' => 'GAY'],
                ['name' => 'Bhagalpur', 'code' => 'BGP'],
                ['name' => 'Purnia', 'code' => 'PRN'],
                ['name' => 'Darbhanga', 'code' => 'DBG'],
                ['name' => 'Begusarai', 'code' => 'BGS'],
                ['name' => 'Samastipur', 'code' => 'SMS'],
                ['name' => 'Rohtas', 'code' => 'RTS'],
                ['name' => 'Nalanda', 'code' => 'NLN'],
                ['name' => 'Katihar', 'code' => 'KTH'],
                ['name' => 'Saharsa', 'code' => 'SHS'],
            ],

            // West Bengal (WB)
            'WB' => [
                ['name' => 'Kolkata', 'code' => 'KOL'],
                ['name' => 'North 24 Parganas', 'code' => 'N24'],
                ['name' => 'South 24 Parganas', 'code' => 'S24'],
                ['name' => 'Howrah', 'code' => 'HWR'],
                ['name' => 'Hooghly', 'code' => 'HGH'],
                ['name' => 'Purba Bardhaman', 'code' => 'PBD'],
                ['name' => 'Paschim Bardhaman', 'code' => 'WBD'],
                ['name' => 'Nadia', 'code' => 'NAD'],
                ['name' => 'Murshidabad', 'code' => 'MSD'],
                ['name' => 'Malda', 'code' => 'MLD'],
                ['name' => 'Jalpaiguri', 'code' => 'JPG'],
                ['name' => 'Darjeeling', 'code' => 'DRJ'],
            ],

            // Odisha (OD)
            'OD' => [
                ['name' => 'Cuttack', 'code' => 'CTC'],
                ['name' => 'Khordha (Bhubaneswar)', 'code' => 'KRD'],
                ['name' => 'Bargarh', 'code' => 'BRG'],
                ['name' => 'Sambalpur', 'code' => 'SBP'],
                ['name' => 'Balasore', 'code' => 'BLS'],
                ['name' => 'Ganjam', 'code' => 'GNJ'],
                ['name' => 'Puri', 'code' => 'PRI'],
                ['name' => 'Bolangir', 'code' => 'BLN'],
                ['name' => 'Kalahandi', 'code' => 'KLH'],
            ],

            // Chhattisgarh (CG)
            'CG' => [
                ['name' => 'Raipur', 'code' => 'RPR'],
                ['name' => 'Durg', 'code' => 'DRG'],
                ['name' => 'Bilaspur', 'code' => 'BSP'],
                ['name' => 'Rajnandgaon', 'code' => 'RJN'],
                ['name' => 'Korba', 'code' => 'KRB'],
                ['name' => 'Raigarh', 'code' => 'RGH'],
                ['name' => 'Janjgir-Champa', 'code' => 'JJC'],
                ['name' => 'Dhamtari', 'code' => 'DMT'],
                ['name' => 'Mahasamund', 'code' => 'MSM'],
            ],

            // Jharkhand (JH)
            'JH' => [
                ['name' => 'Ranchi', 'code' => 'RNC'],
                ['name' => 'Dhanbad', 'code' => 'DHN'],
                ['name' => 'East Singhbhum (Jamshedpur)', 'code' => 'ESB'],
                ['name' => 'Bokaro', 'code' => 'BKR'],
                ['name' => 'Hazaribagh', 'code' => 'HZB'],
                ['name' => 'Deoghar', 'code' => 'DGH'],
                ['name' => 'Giridih', 'code' => 'GRD'],
            ],

            // Kerala (KL)
            'KL' => [
                ['name' => 'Thiruvananthapuram', 'code' => 'TVM'],
                ['name' => 'Ernakulam (Kochi)', 'code' => 'EKM'],
                ['name' => 'Kozhikode', 'code' => 'KKD'],
                ['name' => 'Thrissur', 'code' => 'TCR'],
                ['name' => 'Palakkad', 'code' => 'PLK'],
                ['name' => 'Kottayam', 'code' => 'KTM'],
                ['name' => 'Wayanad', 'code' => 'WYD'],
                ['name' => 'Idukki', 'code' => 'IDK'],
            ],

            // Assam (AS)
            'AS' => [
                ['name' => 'Kamrup Metropolitan (Guwahati)', 'code' => 'KRM'],
                ['name' => 'Nagaon', 'code' => 'NGN'],
                ['name' => 'Sonitpur', 'code' => 'SNT'],
                ['name' => 'Dibrugarh', 'code' => 'DBG'],
                ['name' => 'Jorhat', 'code' => 'JRT'],
                ['name' => 'Cachar (Silchar)', 'code' => 'CCR'],
            ],

            // Himachal Pradesh (HP)
            'HP' => [
                ['name' => 'Shimla', 'code' => 'SML'],
                ['name' => 'Kangra', 'code' => 'KNG'],
                ['name' => 'Kullu', 'code' => 'KLU'],
                ['name' => 'Solan', 'code' => 'SLN'],
                ['name' => 'Mandi', 'code' => 'MND'],
                ['name' => 'Una', 'code' => 'UNA'],
            ],

            // Uttarakhand (UK)
            'UK' => [
                ['name' => 'Dehradun', 'code' => 'DDN'],
                ['name' => 'Haridwar', 'code' => 'HDW'],
                ['name' => 'Udham Singh Nagar', 'code' => 'USN'],
                ['name' => 'Nainital', 'code' => 'NNT'],
            ],

            // Goa (GA)
            'GA' => [
                ['name' => 'North Goa', 'code' => 'NGO'],
                ['name' => 'South Goa', 'code' => 'SGO'],
            ],

            // Delhi (DL)
            'DL' => [
                ['name' => 'North Delhi', 'code' => 'NDL'],
                ['name' => 'South Delhi', 'code' => 'SDL'],
                ['name' => 'East Delhi', 'code' => 'EDL'],
                ['name' => 'West Delhi', 'code' => 'WDL'],
                ['name' => 'Central Delhi', 'code' => 'CDL'],
                ['name' => 'New Delhi', 'code' => 'NED'],
            ],

            // Jammu and Kashmir (JK)
            'JK' => [
                ['name' => 'Srinagar', 'code' => 'SRN'],
                ['name' => 'Jammu', 'code' => 'JMU'],
                ['name' => 'Anantnag', 'code' => 'ANG'],
                ['name' => 'Baramulla', 'code' => 'BRM'],
                ['name' => 'Pulwama', 'code' => 'PLW'],
                ['name' => 'Kathua', 'code' => 'KTH'],
            ],

            // Chandigarh (CH)
            'CH' => [
                ['name' => 'Chandigarh', 'code' => 'CHD'],
            ],

            // Puducherry (PY)
            'PY' => [
                ['name' => 'Puducherry', 'code' => 'PDY'],
                ['name' => 'Karaikal', 'code' => 'KRK'],
            ],
        ];

        foreach ($stateDistricts as $stateCode => $districts) {
            $state = $statesMap->get($stateCode);
            if (! $state) {
                continue;
            }

            foreach ($districts as $index => $dist) {
                $slug = Str::slug($dist['name']);
                District::updateOrCreate(
                    [
                        'state_id' => $state->id,
                        'slug' => $slug,
                    ],
                    [
                        'name' => $dist['name'],
                        'code' => $dist['code'],
                        'sort_order' => $index + 1,
                        'status' => true,
                    ]
                );
            }
        }
    }
}
