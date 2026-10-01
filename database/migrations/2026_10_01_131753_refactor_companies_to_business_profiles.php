<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename table to business_profiles
        if (Schema::hasTable('companies')) {
            Schema::rename('companies', 'business_profiles');
        }

        // 2. Add columns to business_profiles
        Schema::table('business_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('business_profiles', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            }
            if (Schema::hasColumn('business_profiles', 'name')) {
                $table->renameColumn('name', 'company_name');
            }
            if (!Schema::hasColumn('business_profiles', 'business_description')) {
                $table->text('business_description')->nullable();
            }
            if (!Schema::hasColumn('business_profiles', 'trade_preference')) {
                $table->enum('trade_preference', ['buy', 'sell', 'both'])->default('both');
            }
            if (!Schema::hasColumn('business_profiles', 'verification_status')) {
                $table->enum('verification_status', ['approve', 'pending', 'reject', 'inprogress'])->default('pending');
            }
        });

        // 3. Migrate user_has_companies data
        if (Schema::hasTable('user_has_companies') && Schema::hasColumn('user_has_companies', 'company_id') && Schema::hasColumn('user_has_companies', 'user_id')) {
            $userHasCompanies = DB::table('user_has_companies')->get();
            foreach ($userHasCompanies as $mapping) {
                DB::table('business_profiles')
                    ->where('id', $mapping->company_id)
                    ->update(['user_id' => $mapping->user_id]);
            }
        }

        // 4. Update KYC Table
        if (Schema::hasTable('kyc')) {
            // kyc already has user_id and company_id
            if (Schema::hasColumn('kyc', 'company_id')) {
                $kycs = DB::table('kyc')->whereNotNull('company_id')->get();
                foreach ($kycs as $kyc) {
                    $profile = DB::table('business_profiles')->where('id', $kyc->company_id)->first();
                    if ($profile) {
                        DB::table('kyc')->where('id', $kyc->id)->update(['user_id' => $profile->user_id]);
                    }
                }
            }
            Schema::table('kyc', function (Blueprint $table) {
                if (Schema::hasColumn('kyc', 'company_id')) {
                    // Try dropping foreign key. Name is usually kyc_company_id_foreign
                    try {
                        $table->dropForeign('kyc_company_id_foreign');
                    } catch (\Exception $e) {}
                    $table->dropColumn('company_id');
                }
            });
        }

        // 5. Update Business Documents Table
        if (Schema::hasTable('business_documents')) {
            if (Schema::hasColumn('business_documents', 'company_id')) {
                $docs = DB::table('business_documents')->whereNotNull('company_id')->get();
                foreach ($docs as $doc) {
                    $profile = DB::table('business_profiles')->where('id', $doc->company_id)->first();
                    if ($profile) {
                        DB::table('business_documents')->where('id', $doc->id)->update(['user_id' => $profile->user_id]);
                    }
                }
            }
            Schema::table('business_documents', function (Blueprint $table) {
                if (Schema::hasColumn('business_documents', 'company_id')) {
                    try {
                        $table->dropForeign('business_documents_company_id_foreign');
                    } catch (\Exception $e) {}
                    $table->dropColumn('company_id');
                }
            });
        }

        // 6. Rename and Update Company Bank Details
        if (Schema::hasTable('company_bank_details')) {
            Schema::rename('company_bank_details', 'business_profile_bank_details');
        }
        if (Schema::hasTable('business_profile_bank_details')) {
            Schema::table('business_profile_bank_details', function (Blueprint $table) {
                if (!Schema::hasColumn('business_profile_bank_details', 'business_profile_id')) {
                    $table->foreignId('business_profile_id')->nullable()->after('id')->constrained('business_profiles')->cascadeOnDelete();
                }
            });
            if (Schema::hasColumn('business_profile_bank_details', 'company_id')) {
                $banks = DB::table('business_profile_bank_details')->whereNotNull('company_id')->get();
                foreach ($banks as $bank) {
                    DB::table('business_profile_bank_details')->where('id', $bank->id)->update(['business_profile_id' => $bank->company_id]);
                }
            }
            Schema::table('business_profile_bank_details', function (Blueprint $table) {
                if (Schema::hasColumn('business_profile_bank_details', 'company_id')) {
                    try {
                        $table->dropForeign('company_bank_details_company_id_foreign');
                    } catch (\Exception $e) {}
                    $table->dropColumn('company_id');
                }
            });
        }

        // 7. Rename and Update Company Business Categories
        if (Schema::hasTable('company_business_categories')) {
            Schema::rename('company_business_categories', 'business_profile_business_categories');
        }
        if (Schema::hasTable('business_profile_business_categories')) {
            Schema::table('business_profile_business_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('business_profile_business_categories', 'business_profile_id')) {
                    $table->foreignId('business_profile_id')->nullable()->after('id')->constrained('business_profiles')->cascadeOnDelete();
                }
            });
            if (Schema::hasColumn('business_profile_business_categories', 'company_id')) {
                $cats = DB::table('business_profile_business_categories')->whereNotNull('company_id')->get();
                foreach ($cats as $cat) {
                    DB::table('business_profile_business_categories')->where('id', $cat->id)->update(['business_profile_id' => $cat->company_id]);
                }
            }
            Schema::table('business_profile_business_categories', function (Blueprint $table) {
                if (Schema::hasColumn('business_profile_business_categories', 'company_id')) {
                    try {
                        $table->dropForeign('company_business_categories_company_id_foreign');
                    } catch (\Exception $e) {}
                    $table->dropColumn('company_id');
                }
            });
        }

        // 8. Drop Old Columns from business_profiles
        Schema::table('business_profiles', function (Blueprint $table) {
            $colsToDrop = [];
            foreach (['gstin', 'address_line_2', 'pin_code', 'pan_number', 'year_of_establishment', 'no_of_employees', 'website'] as $col) {
                if (Schema::hasColumn('business_profiles', $col)) {
                    $colsToDrop[] = $col;
                }
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });

        // 9. Drop user_has_companies
        Schema::dropIfExists('user_has_companies');
    }

    public function down(): void
    {
        // Reverting this massive structural change accurately is impossible without data loss,
        // but we define the schema reverse where possible.
        
        Schema::create('user_has_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('business_profiles')->cascadeOnDelete();
            $table->timestamps();
        });

        if (Schema::hasTable('business_profiles')) {
            $profiles = DB::table('business_profiles')->whereNotNull('user_id')->get();
            foreach ($profiles as $profile) {
                DB::table('user_has_companies')->insert([
                    'user_id' => $profile->user_id,
                    'company_id' => $profile->id,
                ]);
            }

            Schema::table('business_profiles', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
                $table->renameColumn('company_name', 'name');
                $table->dropColumn(['business_description', 'trade_preference', 'verification_status']);
                $table->string('gstin')->nullable();
                $table->string('address_line_2')->nullable();
                $table->string('pin_code')->nullable();
                $table->string('pan_number')->nullable();
                $table->string('year_of_establishment')->nullable();
                $table->string('no_of_employees')->nullable();
                $table->string('website')->nullable();
            });

            Schema::rename('business_profiles', 'companies');
        }

        if (Schema::hasTable('kyc')) {
            Schema::table('kyc', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            });
            // We can't perfectly recover company_id because we lost it, but we can try to guess from user_id
            $kycs = DB::table('kyc')->whereNotNull('user_id')->get();
            foreach ($kycs as $kyc) {
                $profile = DB::table('companies')->where('user_id', $kyc->user_id)->first();
                if ($profile) {
                    DB::table('kyc')->where('id', $kyc->id)->update(['company_id' => $profile->id]);
                }
            }
        }
        
        if (Schema::hasTable('business_documents')) {
            Schema::table('business_documents', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            });
        }
        
        if (Schema::hasTable('business_profile_bank_details')) {
            Schema::table('business_profile_bank_details', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
                $table->dropForeign(['business_profile_id']);
                $table->dropColumn('business_profile_id');
            });
            Schema::rename('business_profile_bank_details', 'company_bank_details');
        }
        
        if (Schema::hasTable('business_profile_business_categories')) {
            Schema::table('business_profile_business_categories', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
                $table->dropForeign(['business_profile_id']);
                $table->dropColumn('business_profile_id');
            });
            Schema::rename('business_profile_business_categories', 'company_business_categories');
        }
    }
};
