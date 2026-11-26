<?php

declare(strict_types=1);

namespace losthost\OberdeskAPIv1\Controller;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use losthost\DB\DB;
use losthost\DB\DBList;
use losthost\Oberbot\data\api_ticket_request;
use losthost\Oberbot\data\apikey;
use losthost\Oberbot\data\chat_apikey;
use losthost\telle\model\DBPendingJob;

final class TicketsController
{
    private const JOB_CLASS = 'losthost\\Oberbot\\background\\CreateTicketFromApi';

    public int $status = 200;

    private readonly Closure $key_resolver;
    private readonly Closure $permission_checker;
    private readonly Closure $enqueuer;

    public function __construct(?callable $key_resolver = null, ?callable $permission_checker = null, ?callable $enqueuer = null)
    {
        $this->key_resolver = $key_resolver === null
            ? $this->resolveKey(...)
            : Closure::fromCallable($key_resolver);
        $this->permission_checker = $permission_checker === null
            ? $this->canCreateTicket(...)
            : Closure::fromCallable($permission_checker);
        $this->enqueuer = $enqueuer === null
            ? $this->enqueue(...)
            : Closure::fromCallable($enqueuer);
    }

    public function handle(?string $authorization = null, ?string $body = null): array
    {
        $authorization = func_num_args() > 0 ? $authorization : ($_SERVER['HTTP_AUTHORIZATION'] ?? null);
        $body = $body ?? (string) file_get_contents('php://input');

        if (!is_string($authorization) || !preg_match('/^Bearer (\S+)$/', $authorization, $m)) {
            return $this->respond(401, ['ok' => false, 'error' => 'unauthorized']);
        }
        $key = ($this->key_resolver)($m[1]);
        if ($key === null) {
            return $this->respond(401, ['ok' => false, 'error' => 'unauthorized']);
        }

        try {
            [$group_id, $title, $text] = $this->parseBody($body);
        } catch (InvalidArgumentException $e) {
            return $this->respond(400, ['ok' => false, 'error' => $e->getMessage()]);
        }

        if (!($this->permission_checker)($key['id'], $group_id)) {
            return $this->respond(403, ['ok' => false, 'error' => 'forbidden']);
        }

        $request_id = ($this->enqueuer)($key['id'], $group_id, $key['user_id'], $title, $text);
        return $this->respond(200, ['ok' => true, 'request_id' => $request_id]);
    }

    private function parseBody(string $body): array
    {
        $data = json_decode($body, true);
        if (!is_array($data) || array_is_list($data)) {
            throw new InvalidArgumentException('body must be a JSON object');
        }
        if (!isset($data['group_id']) || !is_int($data['group_id']) || $data['group_id'] === 0) {
            throw new InvalidArgumentException('group_id must be a non-zero integer');
        }
        if (!isset($data['title']) || !is_string($data['title']) || $data['title'] === '' || mb_strlen($data['title']) > 128) {
            throw new InvalidArgumentException('title must be a non-empty string up to 128 characters');
        }
        if (!isset($data['text']) || !is_string($data['text']) || $data['text'] === '') {
            throw new InvalidArgumentException('text must be a non-empty string');
        }
        return [$data['group_id'], $data['title'], $data['text']];
    }

    private function respond(int $status, array $data): array
    {
        $this->status = $status;
        if (PHP_SAPI !== 'cli') {
            http_response_code($status);
        }
        return $data;
    }

    private function resolveKey(string $secret): ?array
    {
        $keys = (new DBList(apikey::class, ['secret' => $secret]))->asArray();
        if ($keys === []) {
            return null;
        }
        return ['id' => (int) $keys[0]->id, 'user_id' => (int) $keys[0]->user_id];
    }

    private function canCreateTicket(int $apikey_id, int $chat_id): bool
    {
        $links = (new DBList(chat_apikey::class, ['apikey_id' => $apikey_id, 'chat_id' => $chat_id]))->asArray();
        if ($links === []) {
            return false;
        }
        return in_array('create_ticket', explode(',', (string) $links[0]->permissions), true);
    }

    private function enqueue(int $apikey_id, int $chat_id, int $user_id, string $title, string $text): int
    {
        DB::beginTransaction();
        try {
            $request = new api_ticket_request();
            $request->apikey_id = $apikey_id;
            $request->chat_id = $chat_id;
            $request->user_id = $user_id;
            $request->title = $title;
            $request->text = $text;
            $request->created = new DateTimeImmutable();
            $request->write();
            new DBPendingJob(new DateTimeImmutable(), true, self::JOB_CLASS, (string) $request->id);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return (int) $request->id;
    }
}
