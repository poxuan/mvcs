use Illuminate\Foundation\Http\FormRequset;

abstract class BaseRequset extands FormRequset
{

    protected $rules=[];

    protected $messages=[];                                                                                                       

    public function authorize()  //这个方法可以用来控制访问权限，例如禁止未付费用户访问
    {
        return true; //默认是false，使用时改成true,
    }

    public function rules()
    {
        $rules = $this->rules;
        // \Request::getPathInfo()方法获取命名路由，用来区分不同页面
        $actionName = request()->action();
        if (method_exists($this, $actionName)) {
            // 根据方法不同，验证字段也不同。
            call_user_func_array([$this, $name]);
        }
        return $rules;
    }

    public function messages() { 
        return $this->messages;
    }
}