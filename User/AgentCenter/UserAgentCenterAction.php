<?php
namespace App\Http\SingleActions\Frontend\User\AgentCenter;

use App\Http\Controllers\FrontendApi\FrontendApiMainController;
use Illuminate\Http\JsonResponse;
use App\Models\User\UserProfits;
use Illuminate\Support\Facades\DB;

class UserAgentCenterAction
{
    protected $model ;
    private $selectSum = [
        'sum(team_deposit) as team_deposit',
        'sum(team_withdrawal) as team_withdrawal',
        'sum(team_turnover) as team_turnover',
        'sum(team_prize) as team_prize',
        'sum(team_profit) as team_profit',
        'sum(team_commission) as team_commission',
        'sum(team_dividend) as team_dividend',
        'sum(team_daily_salary) as team_daily_salary',
    ];

    /**
     * UserAgentCenterAction constructor.
     * @param UserProfits $UserProfits
     */
    public function __construct(UserProfits $UserProfits)
    {
        $this->model = $UserProfits;
    }

    /**
     * 团队盈亏api
     * @param FrontendApiMainController $contll
     * @param $request
     * @return JsonResponse
     */
    public function execute(FrontendApiMainController $contll, $request): JsonResponse
    {
        $data = [];
        $sum  = (object)[];

        $request->validate([
            'username' => 'filled|string|alpha_dash',
            'start_data'=>'filled|date',
            'end_data'=>'filled|date',
        ]);

        $username = $request->input('username') ?? '';
        $dateTo = $request->input('date_to') ?? date('Y-m-d');
        $dateFrom = $request->input('date_from') ?? date('Y-m-01');

        $userInfo = $contll->currentAuth->user() ;

        if ($userInfo->parent_id == 0) {
            $where = [['parent_id', $userInfo->id],['date', '>=', $dateFrom], ['date', '<=', $dateTo]];
        } else {
            $where = [['user_id', $userInfo->id],['date', '>=', $dateFrom], ['date', '<=', $dateTo],];
        }

        //区间合计 自己+下属的
        if(empty($username)){
            $sum_team= $this->model->where($where)->select(DB::raw(implode(',', $this->selectSum)))->first();
            $sum_self = $this->model->where([
                ['user_id', $userInfo->id],
                ['date', '>=', $dateFrom],
                ['date', '<=', $dateTo]
            ])->select(DB::raw(implode(',', $this->selectSum)))
                ->first();
            $sum->team_deposit = $sum_team->team_deposit + $sum_self->team_deposit ;
            $sum->team_withdrawal = $sum_team->team_withdrawal + $sum_self->team_withdrawal ;
            $sum->team_turnover = $sum_team->team_turnover + $sum_self->team_turnover ;
            $sum->team_prize = $sum_team->team_prize + $sum_self->team_prize ;
            $sum->team_profit = $sum_team->team_profit + $sum_self->team_profit ;
            $sum->team_commission = $sum_team->team_commission + $sum_self->team_commission ;
            $sum->team_dividend = $sum_team->team_dividend + $sum_self->team_dividend ;
            $sum->team_daily_salary = $sum_team->team_daily_salary + $sum_self->team_daily_salary ;
            $data['sum'] = $sum ;
        }else{
            $data['sum'] = $this->model->where(
                array_merge([['username', $username]],$where)
            )->select(DB::raw(implode(',', $this->selectSum)))
                ->first();
        }
        //自己
        $selectRaw = array_merge(['user_id', 'username'], $this->selectSum);
        $data['self'] = $this->model->where([
            ['date', '>=', $dateFrom],
            ['date', '<=', $dateTo],
            ['user_id', $userInfo->id]
        ])->select(DB::raw(implode(',', $selectRaw)))
            ->first();

        //下级
        $data['child'] = $this->model->where($where)->select(DB::raw(implode(',', $selectRaw)))->groupBy('user_id')->paginate(15);

        return $contll->msgOut(true, $data);
    }
}