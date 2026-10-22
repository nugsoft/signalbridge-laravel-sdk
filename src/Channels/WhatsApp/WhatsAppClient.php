<?php

namespace Nugsoft\SignalBridge\Channels\WhatsApp;

use Illuminate\Http\Client\Response;
use Nugsoft\SignalBridge\Channels\BaseChannelClient;
use Nugsoft\SignalBridge\Exceptions\ValidationException;

/**
 * WhatsApp through SignalBridge.
 *
 * WhatsApp only lets a business start a conversation with a template it has
 * approved, so the flow is: createTemplate() once, wait for the
 * template.approved webhook, then sendTemplate() as often as needed. Free
 * text (send()), files (sendMedia()), reply buttons, lists and link buttons
 * (sendButtons(), sendList(), sendLinkButton()) and Flows (sendFlow()) are
 * delivered only within 24 hours of the person's last message. SignalBridge holds every WhatsApp credential and
 * handles Flow encryption — nothing here needs Meta access.
 */
class WhatsAppClient extends BaseChannelClient
{
  // ── Sending ────────────────────────────────────────────────

  /**
   * Send free text. WhatsApp delivers it only within 24 hours of the
   * person's last message; to start a conversation use sendTemplate().
   *
   * @param  array  $options  metadata, scheduled_at
   */
  public function send(string $recipient, string $message, array $options = []): array
  {
    $this->requireRecipient($recipient);

    if (trim($message) === '') {
      throw new ValidationException('Message content is required');
    }

    if (mb_strlen($message) > 4096) {
      throw new ValidationException('WhatsApp message exceeds maximum length of 4096 characters');
    }

    return $this->post('whatsapp/send', ['recipient' => $recipient, 'message' => $message] + $this->common($options));
  }

  /**
   * Send one of your approved templates.
   *
   * @param  list<string>  $variables  Values for {{1}}, {{2}}, … in order (the code, for an authentication template)
   * @param  array  $options  language, header (['type' => 'document'|'image'|'video', 'url' => …, 'filename' => …]),
   *                          flow (['data' => […]] for a template with a Flow button), metadata, scheduled_at
   *
   * @example SignalBridge::whatsapp()->sendTemplate('256700000000', 'fee_reminder', ['John', 'UGX 50,000'])
   */
  public function sendTemplate(string $recipient, string $templateName, array $variables = [], array $options = []): array
  {
    $this->requireRecipient($recipient);

    if (trim($templateName) === '') {
      throw new ValidationException('Template name is required');
    }

    foreach ($variables as $value) {
      if (is_array($value)) {
        throw new ValidationException(
          'sendTemplate() takes the variables as a plain list, e.g. [\'John\', \'UGX 50,000\']. '
          .'The Meta "components" structure is built by SignalBridge.'
        );
      }
    }

    return $this->post('whatsapp/send', array_filter([
      'recipient' => $recipient,
      'template' => $templateName,
      'variables' => array_values(array_map('strval', $variables)),
      'language' => $options['language'] ?? null,
      'header' => $options['header'] ?? null,
      'flow' => $options['flow'] ?? null,
    ], fn ($value) => $value !== null) + $this->common($options));
  }

  /**
   * Send one of your published Flows as an interactive message (within 24
   * hours of the person's last message). The answers arrive as a
   * flow.completed webhook.
   *
   * @param  array  $options  header, footer, screen (open on this screen; left out, your data endpoint is asked),
   *                          data (passed to that screen), metadata, scheduled_at
   */
  public function sendFlow(string $recipient, string $flowName, string $body, string $button, array $options = []): array
  {
    $this->requireRecipient($recipient);

    return $this->post('whatsapp/send', [
      'recipient' => $recipient,
      'flow' => array_filter([
        'name' => $flowName,
        'body' => $body,
        'button' => $button,
        'header' => $options['header'] ?? null,
        'footer' => $options['footer'] ?? null,
        'screen' => $options['screen'] ?? null,
        'data' => $options['data'] ?? null,
      ], fn ($value) => $value !== null),
    ] + $this->common($options));
  }

  /**
   * The files WhatsApp sends as a message of their own.
   */
  public const MEDIA_TYPES = ['image', 'document', 'video', 'audio', 'sticker'];

  /**
   * Send a file on its own: an image, document, video, audio (a voice note,
   * say) or sticker. WhatsApp downloads it from the https link, and
   * delivers it only within 24 hours of the person's last message.
   *
   * @param  array<string, mixed>  $options  caption (image, video, document), filename (document), metadata, scheduled_at
   * @return array<string, mixed>
   */
  public function sendMedia(string $recipient, string $type, string $url, array $options = []): array
  {
    $this->requireRecipient($recipient);

    if (! in_array($type, self::MEDIA_TYPES, true)) {
      throw new ValidationException('The media type must be one of: '.implode(', ', self::MEDIA_TYPES));
    }

    if (! str_starts_with($url, 'https://')) {
      throw new ValidationException('The media URL must be a public https link WhatsApp can download the file from');
    }

    return $this->post('whatsapp/send', [
      'recipient' => $recipient,
      'media' => array_filter([
        'type' => $type,
        'url' => $url,
        'caption' => $options['caption'] ?? null,
        'filename' => $options['filename'] ?? null,
      ], fn ($value) => $value !== null),
    ] + $this->common($options));
  }

  /**
   * Send up to three reply buttons (within 24 hours of the person's last
   * message). A tap arrives as a message.received webhook whose
   * content.reply_id is the button's id.
   *
   * @param  array<string|int, mixed>  $buttons  ['confirm_4821' => 'Confirm', …], or [['id' => …, 'title' => …], …]
   * @param  array<string, mixed>  $options  header (text, or ['type' => 'image'|'video'|'document', 'url' => …]), footer, metadata, scheduled_at
   * @return array<string, mixed>
   */
  public function sendButtons(string $recipient, string $body, array $buttons, array $options = []): array
  {
    $this->requireRecipient($recipient);

    $buttons = array_map(
      fn ($title, $id) => is_array($title) ? $title : ['id' => (string) $id, 'title' => (string) $title],
      $buttons,
      array_keys($buttons)
    );

    if ($buttons === [] || count($buttons) > 3) {
      throw new ValidationException('WhatsApp allows 1 to 3 reply buttons. Use sendList() for more options.');
    }

    return $this->sendInteractive($recipient, ['type' => 'buttons', 'body' => $body, 'buttons' => $buttons], $options);
  }

  /**
   * Send a list: one button that opens up to 10 options, in sections
   * (within 24 hours of the person's last message). A pick arrives as a
   * message.received webhook whose content.reply_id is the option's id.
   *
   * @param  list<array<string, mixed>>  $sections  [['title' => 'Kampala', 'rows' => [['id' => …, 'title' => …, 'description' => …], …]], …]
   * @param  array<string, mixed>  $options  header (text only), footer, metadata, scheduled_at
   * @return array<string, mixed>
   */
  public function sendList(string $recipient, string $body, string $button, array $sections, array $options = []): array
  {
    $this->requireRecipient($recipient);

    $rows = array_sum(array_map(fn ($section) => count($section['rows'] ?? []), $sections));

    if ($rows === 0 || $rows > 10) {
      throw new ValidationException('A WhatsApp list has 1 to 10 options across all its sections.');
    }

    return $this->sendInteractive($recipient, ['type' => 'list', 'body' => $body, 'button' => $button, 'sections' => $sections], $options);
  }

  /**
   * Send a button that opens a link, without showing the URL (within 24
   * hours of the person's last message).
   *
   * @param  array<string, mixed>  $options  header (text, or ['type' => 'image'|'video'|'document', 'url' => …]), footer, metadata, scheduled_at
   * @return array<string, mixed>
   */
  public function sendLinkButton(string $recipient, string $body, string $button, string $url, array $options = []): array
  {
    $this->requireRecipient($recipient);

    if (! str_starts_with($url, 'https://')) {
      throw new ValidationException('The link must be an https URL');
    }

    return $this->sendInteractive($recipient, ['type' => 'cta_url', 'body' => $body, 'button' => $button, 'url' => $url], $options);
  }

  /**
   * @param  array<string, mixed>  $interactive
   * @param  array<string, mixed>  $options
   * @return array<string, mixed>
   */
  protected function sendInteractive(string $recipient, array $interactive, array $options): array
  {
    $header = $options['header'] ?? null;

    return $this->post('whatsapp/send', [
      'recipient' => $recipient,
      'interactive' => array_filter([
        ...$interactive,
        'header' => is_string($header) ? ['type' => 'text', 'text' => $header] : $header,
        'footer' => $options['footer'] ?? null,
      ], fn ($value) => $value !== null),
    ] + $this->common($options));
  }

  // ── Templates ──────────────────────────────────────────────

  /**
   * @param  string|null  $status  pending, approved, rejected, paused or disabled
   */
  public function listTemplates(?string $status = null): array
  {
    return $this->get('whatsapp/templates', array_filter(['status' => $status]));
  }

  /**
   * @param  bool  $refresh  Ask WhatsApp for the latest review status
   */
  public function getTemplate(int $templateId, bool $refresh = false): array
  {
    return $this->get("whatsapp/templates/{$templateId}", $refresh ? ['refresh' => 1] : []);
  }

  /**
   * Submit a template for WhatsApp's review.
   *
   * @param  array  $template  name, category (utility|marketing|authentication), language, body, examples,
   *                           header, footer, buttons — or, for authentication, code_expiration_minutes
   *
   * @example SignalBridge::whatsapp()->createTemplate([
   *     'name' => 'fee_reminder',
   *     'category' => 'utility',
   *     'body' => 'Hello {{1}}, your fee balance is {{2}}. Please pay by Friday.',
   *     'examples' => ['John', 'UGX 50,000'],
   * ])
   */
  public function createTemplate(array $template): array
  {
    return $this->post('whatsapp/templates', $template);
  }

  public function deleteTemplate(int $templateId): array
  {
    return $this->delete("whatsapp/templates/{$templateId}");
  }

  // ── Flows ──────────────────────────────────────────────────

  public function listFlows(): array
  {
    return $this->get('whatsapp/flows');
  }

  public function getFlow(int $flowId, bool $refresh = false): array
  {
    return $this->get("whatsapp/flows/{$flowId}", $refresh ? ['refresh' => 1] : []);
  }

  /**
   * Create a Flow as a draft from its JSON. Give endpoint_url if it fetches
   * live data: SignalBridge forwards those calls there as plain JSON, signed
   * with the endpoint_secret in the response (shown once).
   *
   * @param  array  $flow  name, categories, flow_json (array or string), endpoint_url
   */
  public function createFlow(array $flow): array
  {
    return $this->post('whatsapp/flows', $flow);
  }

  /**
   * @param  array  $changes  flow_json (drafts only), endpoint_url
   */
  public function updateFlow(int $flowId, array $changes): array
  {
    return $this->request('put', "whatsapp/flows/{$flowId}", $changes);
  }

  public function publishFlow(int $flowId): array
  {
    return $this->post("whatsapp/flows/{$flowId}/publish");
  }

  public function regenerateFlowSecret(int $flowId): array
  {
    return $this->post("whatsapp/flows/{$flowId}/regenerate-secret");
  }

  /**
   * Delete a draft, or retire a published Flow.
   */
  public function deleteFlow(int $flowId): array
  {
    return $this->delete("whatsapp/flows/{$flowId}");
  }

  // ── Received messages ──────────────────────────────────────

  /**
   * Messages customers sent you, newest first.
   *
   * @param  array  $filters  from, type, since, per_page, page
   */
  public function received(array $filters = []): array
  {
    return $this->get('whatsapp/received', $filters);
  }

  public function getReceived(int $messageId): array
  {
    return $this->get("whatsapp/received/{$messageId}");
  }

  /**
   * The file a customer sent (photo, document, voice note, video), as raw bytes.
   */
  public function downloadMedia(int $messageId): string
  {
    $response = $this->http()->get("{$this->baseUrl}/whatsapp/received/{$messageId}/media");

    if ($response->failed()) {
      $this->handleError($response);
    }

    return $response->body();
  }

  // ── Plumbing ───────────────────────────────────────────────

  protected function requireRecipient(string $recipient): void
  {
    if (trim($recipient) === '') {
      throw new ValidationException('Recipient phone number is required');
    }
  }

  /**
   * @return array<string, mixed>
   */
  protected function common(array $options): array
  {
    return array_filter([
      'metadata' => $options['metadata'] ?? null,
      'scheduled_at' => $options['scheduled_at'] ?? null,
    ], fn ($value) => $value !== null);
  }

  protected function get(string $path, array $query = []): array
  {
    return $this->handle($this->http()->get("{$this->baseUrl}/{$path}", $query));
  }

  protected function post(string $path, array $data = []): array
  {
    return $this->request('post', $path, $data);
  }

  protected function delete(string $path): array
  {
    return $this->handle($this->http()->delete("{$this->baseUrl}/{$path}"));
  }

  protected function request(string $method, string $path, array $data): array
  {
    return $this->handle($this->http()->{$method}("{$this->baseUrl}/{$path}", $data));
  }

  protected function handle(Response $response): array
  {
    if ($response->failed()) {
      $this->handleError($response);
    }

    return $this->parseResponse($response);
  }
}
