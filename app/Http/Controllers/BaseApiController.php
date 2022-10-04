<?php


namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @method show($id)
 * @method store
 * @method update
 * @method destroy
 */
/**
 * @OA\Info(title="Yosr API", version="2.0")
 */
class BaseApiController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ApiResponse;

    /** @var Model $class */
    protected $class = null;
    protected $with = null;
    protected $withCount = null;
    protected $select = null;
    protected $selectDetail = null;
    protected $withDetail = null;

    public function index()
    {
        /** @var Builder $query */

        $query = $this->class::query();

        if ($this->with != null)
            $query = $query->with($this->with);
        if ($this->select != null)
            $query = $query->select($this->select);
        $query = $query->orderByDesc("id");

        return $this->sendResponse($query->paginate($this->get_per_page()));

    }


    /**
     * @param Builder $query
     * @return Builder
     */
    protected function getLimitedQuery(Builder $query): Builder
    {
        return $query->limit($this->getLimit())->offset($this->getOffset());
    }

    protected function getLimit()
    {
        $limit = $this->getRequest()->input('limit', 10);
        if (!is_numeric($limit) || $limit <= 0) {
            $limit = 5;
        }
        return $limit;
    }

    protected function get_per_page()
    {
        $per_page = $this->getRequest()->input('per_page', 5);
        if (!is_numeric($per_page) || $per_page <= 0) {
            $per_page = 5;
        }
        return $per_page;
    }

    /**
     * @return Request
     */
    protected function getRequest(): Request
    {
        return app('request');
    }

    protected function getOffset()
    {
        $offset = $this->getRequest()->input('offset', 0);
        if (!is_numeric($offset) || $offset < 0) {
            $offset = 0;
        }
        return $offset;
    }

    protected function applyFilters(Request $request, Builder $query,$module=null)
    {
        //Search with title
        if ($request->input('title') != null)
            $query = $query->where('title', 'like', '%' . $request->input('title') . '%');

        //Search with title
        if ($request->input('is_contain_360') != null)
            $query = $query->whereNotNull('link_360') ;

        // فلترة حسب المدن
        if ($request->input('cities') != null)
            $query = $query->whereIn('city_id', $request->input('cities'));

        // فلترة حسب البلدان
        if ($request->input('countries') != null)
            $query = $query->whereIn('country_id', $request->input('countries'));

        // فلترة حسب الاهتمامات
        /* if ($request->input('interests')) {
             $query = $query->whereIn('category_id', $request->input('interests'));
         }*/
        if ($request->input('interests')) {
            $interests = $request->input('interests');
            $category = Category::with('grandchildren')->findOrFail($interests[0]);

            if ($category->grandchildren->all() == []) {
                $query = $query->where('category_id', $category->id);

            } else {
                // get categories and all of children
                $category_children = [$category->id];
                $categories = $category->grandchildren->all();
                while (count($categories) > 0) {
                    $nextCategories = [];
                    foreach ($categories as $category) {
                        $nextCategories = array_merge($nextCategories, $category->grandchildren->all());
                        $category_children[] = $category->id;
                    }
                    $categories = $nextCategories;
                }
                $query = $query->whereIn('category_id', $category_children);
            }
        }
        // فلترة حسب التاريخ
        if ($request->input('from') && $request->input('to')) {
            $query = $query->whereBetween('published_at', [$request->input('from'),
                $request->input('to')]);
        }

        // فلترة حسب الجنس
        if ($request->input('gender')) {
            $query = $query->where('is_sponsored', 1)
                ->with('sponsored')
                ->whereHas('sponsored', function ($query) use ($request) {
                    return $query->where('sponsored_modules.gender', $request->input('gender'));
                });
        }
        if ($request->input('user_id')) {
            $query = $query->where('user_id', $request->input('user_id'));
        }

        // فلترة حسب الإعلان الممول وغير الممول
        if ($request->input('is_sponsored') == "1") {
            $query = $query->where('is_sponsored', 1);
        } else {
            if ($request->input('is_sponsored') == "0")
                $query = $query->where('is_sponsored', 0);
        }



        // published_status check
        $publishedStatus = $request->input('published_status', "publish");
        $query = $query->where('published_status', $publishedStatus);

        // فلترة حسب مفعل او غير مفعل
        $active = $request->input('is_active', 1);
        $is_archive = $request->input('is_archive', 0);
        if ($active == 0) {
            $query = $query->where('active', 0);
        } else {
            if ($is_archive != 1 && $publishedStatus == "publish") {
                $query = $query->where('active', 1);
            }
        }
        if ($is_archive == 1) {
            $query = $query->where('expiration_date', '<', Carbon::now()->format('Y-m-d H:i:s'));
        } else {
            if($module =='auction'){
                $query = $query->where(function ($q) {
                    $number_of_days_after_for_expire_auction = Setting::get("number_of_days_after_for_expire_auction") ?? 10;

                    return $q->whereRaw('expiration_date + interval '.$number_of_days_after_for_expire_auction.' day >= ?', Carbon::now()->format('Y-m-d H:i:s'))
                        ->orWhere('expiration_date', null);
                });
            }else{
                $query = $query->where(function ($q) {
                    return $q->where('expiration_date', '>=', Carbon::now()->format('Y-m-d H:i:s'))->orWhere('expiration_date', null);
                });
            }
        }

        if ($request->input('search_term') != null) {
            $term = $request->input('search_term');
            $query = $query->where(function ($query) use ($term) {
                $query->where('title', "like", "%" . $term . "%")
                    ->orWhere('description', "like", "%" . $term . "%");
            });
        }


        return $query;
    }

    protected function ApplyFiltersByPoints(Request $request, Builder $query)
    {
        if ($request->input('from_points') || $request->input('to_points')) {
            $query = $query->where('is_sponsored', 1)
                ->with('sponsored')
                ->whereHas('sponsored', function ($query) use ($request) {
                    return $query->whereBetween('sponsored_modules.value_per_user',
                        [$request->input('from_points'), $request->input('to_points')]
                    );
                });
        }

        return $query;
    }

    protected function applySort(Request $request, Builder $query, $module_type)
    {
        switch ($request->input('sort_by')) {
            // order by created_at date
            case "date_asc":
                $query = $query->oldest();
                break;
            case "date_desc":
                $query = $query->latest();
                break;
            // order by price
            case "price_asc":
                if ($module_type == 'ad')
                    $query = $query->orderBy(DB::raw('ISNULL(price),price'), 'ASC');
                elseif ($module_type == 'auction')
                    $query = $query->orderBy(DB::raw('ISNULL(current_price),current_price'), 'ASC');
                // reset to default: order by id desc
                else {
                    $query = $query->orderByDesc("id");
                }
                break;
            case "price_desc":
                if ($module_type == 'ad')
                    $query = $query->orderBy(DB::raw('ISNULL(price),price'), 'DESC');
                elseif ($module_type == 'auction')
                    $query = $query->orderBy(DB::raw('ISNULL(current_price),current_price'), 'DESC');
                // reset to default: order by id desc
                else {
                    $query = $query->orderByDesc("id");
                }
                break;
        }
        return $query;
    }

    protected function beforeShow(Builder $query)
    {
        return $query;
    }

}
