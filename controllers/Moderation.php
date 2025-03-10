<?php

namespace Marketplace\Tokens\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\Backend;
use Backend\Facades\BackendAuth;
use BackendMenu;
use Marketplace\Moderationhistory\Models\ModeartionHistory;
use Marketplace\ReasonsRejection\Models\ReasonsRejection;
use Marketplace\Tokens\Models\Token;
use October\Rain\Support\Facades\Flash;

class Moderation extends Controller
{
    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController'
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        $this->setMenuContext();
    }

    /**
     * October CMS method.
     * Index action, lists the tokens.
     *
     * @return void
     */
    public function index(): void
    {
        $this->setMenuContext(); // Ensure menu context is set
        $this->asExtension('ListController')->index();
    }

    /**
     * October CMS method.
     * Update action, allows for editing tokens.
     *
     * @param int|null $recordId
     * @param string|null $context
     * @return void
     */
    public function update($id = null, $context = null)
    {
        $token = Token::find($id);

        if ($token) {
            $this->vars['token'] = $token;
        }

        $this->setMenuContext($token->moderation_status_id);
        $this->pageTitle = $token->name;

        return $this->asExtension('FormController')->update($id, $context);
    }

    /**
     * October CMS method.
     * Extends the list query to filter tokens by moderation status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return void
     */
    public function listExtendQuery($query): void
    {
        $moderationStatusId = get('moderation_status_id');
        if ($moderationStatusId) {
            $query->where('moderation_status_id', $moderationStatusId);
        }
    }

    /**
     * Sets the backend menu context based on the moderation status.
     *
     * @return void
     */
    protected function setMenuContext($moderationStatusId = null): void
    {
        if (!$moderationStatusId) {
            $moderationStatusId = get('moderation_status_id');
        }

        $mainMenu = 'main-menu-moderation-tokens';

        switch ($moderationStatusId) {
            case 1: // Pending
                BackendMenu::setContext('Marketplace.Tokens', $mainMenu, 'side-menu-moderation');
                break;
            case 2: // Rejected
                BackendMenu::setContext('Marketplace.Tokens', $mainMenu, 'side-menu-moderation-rejected');
                break;
            case 3: // Approved
                BackendMenu::setContext('Marketplace.Tokens', $mainMenu, 'side-menu-moderation-approved');
                break;
            case 4: // Deferred
                BackendMenu::setContext('Marketplace.Tokens', $mainMenu, 'side-menu-moderation-deferred');
                break;
            default:
                BackendMenu::setContext('Marketplace.Tokens', $mainMenu);
                break;
        }
    }

    /**
     * Approves the token and redirects to the next pending token for moderation.
     *
     * @param int|null $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function onApprove($id = null)
    {
        $token = Token::find($id);
        if (!$token) {
            Flash::error('Токен не найден');
            return redirect()->back();
        }

        $currentStatusId = $token->moderation_status_id;
        $token->moderation_status_id = 3; // Approved status
        $token->save();

        // $this->createModerationHistory($token);

        Flash::success('Токен утвержден');

        return $this->redirectAfterModeration($currentStatusId);
    }

    public function onShowRejectModal($id = null)
    {
        $reasonsRejection = ReasonsRejection::all();
        $token = Token::find($id);

        $this->vars['token'] = $token;
        $this->vars['reasonsRejection'] = $reasonsRejection;

        return $this->makePartial('reject_modal');
    }

    public function onReject($id = null)
    {
        $token = Token::find($id);
        if (!$token) {
            Flash::error('Токен не найден');
            return redirect()->back();
        }

        $currentStatusId = $token->moderation_status_id;
        $comment = post('Token[comment]');
        $reasonRejectionId = post('Token[reasons_rejection]', null);

        // Update token with rejection details
        $token->moderation_status_id = 2; // Rejected status
        $token->reasons_rejection_id = $reasonRejectionId;
        $token->comment = $comment;
        $token->save();

        // $this->createModerationHistory($token);

        Flash::success('Токен отклонен');

        return $this->redirectAfterModeration($currentStatusId);
    }

    /**
     * Defers the token and redirects to the next pending token for moderation.
     *
     * @param int|null $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function onDefer($id = null)
    {
        $token = Token::find($id);
        if (!$token) {
            Flash::error('Токен не найден');
            return redirect()->back();
        }

        $currentStatusId = $token->moderation_status_id;

        $token->moderation_status_id = 4; // Deferred status
        $token->save();

        // $this->createModerationHistory($token);

        Flash::success('Токен отложен');

        return $this->redirectAfterModeration($currentStatusId);
    }

    function onApproveSelected()
    {
        $tokenIds = post('checked', []);

        $count = 0;
        $currentStatusId = 1;
        Token::query()
            ->whereIn('id', $tokenIds)
            ->each(function (Token $token) use (&$count, &$currentStatusId) {
                $currentStatusId = $token->moderation_status_id;
                $token->moderation_status_id = 3; // Approved status
                $token->save();

                // $this->createModerationHistory($token);
                $count++;
            });

        Flash::success("Промодерировано токенов: $count ");

        return redirect()->to(Backend::url('marketplace/tokens/moderation?moderation_status_id=' . $currentStatusId));
    }

    public function onSaveHiddenComment($id = null)
    {
        $hiddenComment = post('Token[hidden_comment]');

        $token = Token::find($id);

        $token->hidden_comment = $hiddenComment;
        $token->save();

        Flash::success('Скрытый комментарий сохранен');
    }

    /**
     * Create moderation history for the token.
     *
     * @param \Marketplace\Tokens\Models\Token $token
     * @param string $comment
     * @return void
     */
    protected function createModerationHistory(Token $token): void
    {
        ModeartionHistory::create([
            'token_id' => $token->id,
            'moderation_status_id' => $token->moderation_status_id,
            'reasons_rejection_id' => $token->reasons_rejection_id,
            'comment' => $token->comment,
            'type' => 'Токен',
            'moderator_id' => BackendAuth::getUser()->id,
        ]);
    }

    /**
     * Redirects to the next token for moderation or back to the list.
     *
     * @param int $currentStatusId
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectAfterModeration($currentStatusId)
    {
        // Redirect to the next token with the same current status
        $nextToken = Token::where('moderation_status_id', $currentStatusId)->orderBy('id', 'asc')->first();
        if ($nextToken) {
            return redirect()->to(Backend::url('marketplace/tokens/moderation/update/' . $nextToken->id));
        }

        return redirect()->to(Backend::url('marketplace/tokens/moderation?moderation_status_id=' . $currentStatusId));
    }
}
