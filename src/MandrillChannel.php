<?php

namespace NotificationChannels\Mandrill;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Mandrill;

class MandrillChannel
{
    /** @var \Mandrill */
    protected $mandrill;

    /**
     * Constructs an instance of the channel.
     *
     * @param \Mandrill $mandrill
     */
    public function __construct(Mandrill $mandrill)
    {
        $this->mandrill = $mandrill;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed                                  $notifiable
     * @param  \Illuminate\Notifications\Notification $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $to = $notifiable->routeNotificationFor('Mandrill');

        $message = $notification->toMandrill($notifiable)
            ->to($this->parseTo($to))
            ->toArray();

        // Inject global "From" address if it's not in the message.
        if (empty($message['from_email']) && !empty(config('mail.from.address'))) {
            Arr::set($message, 'message.from_email', config('mail.from.address'));
        }

        if (empty($message['from_name']) && !empty(config('mail.from.name'))) {
            Arr::set($message, 'message.from_name', config('mail.from.name'));
        }

        // Forward all emails if enabled.
        if (config('services.mandrill.forward.enabled', false)) {
            Arr::set($message, 'message.to', [
                ['email' => config('services.mandrill.forward.email')],
            ]);
        }

        $arguments = [];

        if (!empty($message['template_name'])) {
            $method = 'sendTemplate';
            $arguments[] = Arr::get($message, 'template_name');
            $templateContents = Arr::get($message, 'template_content', []);
            $templateContents = array_merge($this->getGlobalMerge(), $templateContents);
            $arguments[] = $templateContents;

            $message['message']['global_merge_vars'] = array_merge(!empty($message['message']['global_merge_vars']) ? $message['message']['global_merge_vars'] : [], $templateContents);
        } else {
            $method = 'send';
        }

        $arguments[] = Arr::get($message, 'message', []);
        $arguments[] = Arr::get($message, 'async', false);
        $arguments[] = Arr::get($message, 'ip_pool');
        $arguments[] = Arr::get($message, 'send_at');

        $this->mandrill->messages->{$method}(...$arguments);
    }

    /**
     * Parse mailing list.
     *
     * @param  array $to
     * @return array
     */
    protected function parseTo(array $to = [])
    {
        if (Arr::has($to, 'email')) {
            return [$to];
        }

        return $to;
    }

    /**
     * Get global vars to template
     *
     * @return array
     */
    protected function getGlobalMerge()
    {
        $config = [];

        if (!empty(config('fund.mandrill')) and is_array(config('fund.mandrill'))) {
            $i = 0;
            foreach (config('fund.mandrill') as $key => $value) {
                $config[$i]['name'] = $key;
                $config[$i]['content'] = $value;
                $i++;
            }
        }

        return $config;
    }
}
