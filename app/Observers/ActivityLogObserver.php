<?php

namespace App\Observers;

use App\Models\User;
use App\Services\SystemActivityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

class ActivityLogObserver
{
    /**
     * Mapping of model class names to human-readable module names.
     *
     * @var array<class-string, string>
     */
    public const MODULE_MAP = [
        \App\Models\SiteSetting::class => 'Website',
        \App\Models\SmtpSetting::class => 'Settings',
        \App\Models\FirebaseSetting::class => 'Settings',
        \App\Models\NewsArticle::class => 'News',
        \App\Models\NewsCategory::class => 'News',
        \App\Models\NewsSource::class => 'News',
        \App\Models\NewsMedia::class => 'News',
        \App\Models\Mandi::class => 'Mandis',
        \App\Models\State::class => 'Locations',
        \App\Models\District::class => 'Locations',
        \App\Models\Commodity::class => 'Commodities',
        \App\Models\CommodityCategory::class => 'Commodities',
        \App\Models\CommoditySubcategory::class => 'Commodities',
        \App\Models\CommodityVariety::class => 'Commodities',
        \App\Models\CommodityGrade::class => 'Commodities',
        \App\Models\Exchange::class => 'Exchanges',
        \App\Models\ExchangeCommodityMapping::class => 'Exchanges',
        \App\Models\ExchangeInstrument::class => 'Exchanges',
        \App\Models\User::class => 'Users',
        \App\Models\Role::class => 'Roles',
        \App\Models\Company::class => 'Users',
        \App\Models\EmailTemplate::class => 'Email Templates',
        \App\Models\NotificationTemplate::class => 'Notifications',
        \App\Models\NotificationTopic::class => 'Notifications',
        \App\Models\NotificationBatch::class => 'Notifications',
    ];

    /**
     * Sensitive attributes that must never be recorded in audit diffs.
     *
     * @var list<string>
     */
    protected const SENSITIVE_FIELDS = [
        'password',
        'remember_token',
        'plain_token',
        'smtp_password',
        'fcm_service_account_json',
        'service_account_json',
        'updated_at',
        'last_login_at',
    ];

    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        try {
            $module = $this->resolveModule($model);
            $label = $this->resolveLabel($model);
            $identifier = $this->resolveIdentifier($model);

            $attributes = $model->attributesToArray();
            foreach (self::SENSITIVE_FIELDS as $field) {
                unset($attributes[$field]);
            }

            $description = "Created {$label}: {$identifier}";

            SystemActivityService::log(
                module: $module,
                action: 'Created',
                description: $description,
                properties: [
                    'model' => class_basename($model),
                    'id' => $model->getKey(),
                    'attributes' => $attributes,
                ]
            );
        } catch (Throwable) {
            // Fail-safe: Observers must never interrupt the primary transaction
        }
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        try {
            $dirty = $model->getDirty();
            foreach (self::SENSITIVE_FIELDS as $field) {
                unset($dirty[$field]);
            }

            if (empty($dirty)) {
                return;
            }

            $module = $this->resolveModule($model);
            $label = $this->resolveLabel($model);
            $identifier = $this->resolveIdentifier($model);

            $action = 'Updated';
            if (count($dirty) === 1 && (isset($dirty['status']) || isset($dirty['is_active']))) {
                $action = 'Status Changed';
                $newStatus = $dirty['status'] ?? $dirty['is_active'];
                $description = "Changed status of {$label} to {$newStatus}: {$identifier}";
            } elseif ($model instanceof \App\Models\SiteSetting) {
                $fieldNames = [
                    'site_name' => 'Site Name',
                    'site_title' => 'Site Title',
                    'site_description' => 'Site Description',
                    'email' => 'Contact Email',
                    'phone_number' => 'Phone Number',
                    'social_links' => 'Social Links',
                    'timezone' => 'Timezone',
                    'default_language' => 'Default Language',
                    'currency' => 'Currency',
                    'web_logo' => 'Web Logo',
                    'mobile_logo' => 'Mobile Logo',
                    'favicon' => 'Favicon',
                ];
                $changed = [];
                foreach (array_keys($dirty) as $field) {
                    $changed[] = $fieldNames[$field] ?? ucwords(str_replace('_', ' ', $field));
                }
                $changedStr = !empty($changed) ? implode(', ', $changed) : 'General Settings';
                $description = "Updated site settings ({$changedStr}): {$identifier}";
            } else {
                $description = "Updated {$label}: {$identifier}";
            }

            $old = array_intersect_key($model->getOriginal(), $dirty);
            foreach (self::SENSITIVE_FIELDS as $field) {
                unset($old[$field]);
            }
            SystemActivityService::log(
                module: $module,
                action: $action,
                description: $description,
                properties: [
                    'model' => class_basename($model),
                    'id' => $model->getKey(),
                    'before' => $old,
                    'after' => $dirty,
                ]
            );
        } catch (Throwable) {
            // Fail-safe
        }
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        try {
            $module = $this->resolveModule($model);
            $label = $this->resolveLabel($model);
            $identifier = $this->resolveIdentifier($model);

            SystemActivityService::log(
                module: $module,
                action: 'Deleted',
                description: "Deleted {$label}: {$identifier}",
                properties: [
                    'model' => class_basename($model),
                    'id' => $model->getKey(),
                ]
            );
        } catch (Throwable) {
            // Fail-safe
        }
    }

    /**
     * Resolve the module name for a model.
     */
    protected function resolveModule(Model $model): string
    {
        $class = get_class($model);

        return self::MODULE_MAP[$class] ?? Str::headline(class_basename($model));
    }

    /**
     * Resolve a human-friendly model label (e.g. 'news article', 'mandi').
     */
    protected function resolveLabel(Model $model): string
    {
        if ($model instanceof \App\Models\SiteSetting) {
            return 'site settings';
        }

        $base = class_basename($model);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $base));
    }

    /**
     * Resolve the identifying title or name of the record.
     */
    protected function resolveIdentifier(Model $model): string
    {
        if ($model instanceof \App\Models\SiteSetting) {
            return !empty($model->site_name) ? (string) $model->site_name : 'General Settings';
        }

        if ($model instanceof \App\Models\SmtpSetting) {
            return !empty($model->host) ? (string) $model->host : 'SMTP Configuration';
        }

        if ($model instanceof \App\Models\FirebaseSetting) {
            return !empty($model->project_id) ? (string) $model->project_id : 'Firebase Configuration';
        }

        if (!empty($model->site_name)) {
            return (string) $model->site_name;
        }

        if (!empty($model->site_title)) {
            return (string) $model->site_title;
        }

        if (!empty($model->title)) {
            return (string) $model->title;
        }

        if (!empty($model->name)) {
            return (string) $model->name;
        }

        if (!empty($model->full_name)) {
            return (string) $model->full_name;
        }

        if (!empty($model->username)) {
            return (string) $model->username;
        }

        if (!empty($model->email)) {
            return (string) $model->email;
        }

        if (!empty($model->code)) {
            return (string) $model->code;
        }

        return '#' . $model->getKey();
    }
}
