<?php
/**
 * A base controller for CodeIgniter with view autoloading, layout support,
 * model loading, helper loading, asides/partials and per-controller 404
 *
 * @link http://github.com/jamierumbelow/codeigniter-base-controller
 * @copyright Copyright (c) 2012, Jamie Rumbelow <http://jamierumbelow.net>
 */

class MY_Controller extends CI_Controller
{

    /* --------------------------------------------------------------
     * VARIABLES
     * ------------------------------------------------------------ */

    /**
     * The current request's view. Automatically guessed
     * from the name of the controller and action
     */
    protected $view = '';

    /**
     * An array of variables to be passed through to the
     * view, layout and any asides
     */
    protected $data = array();

    /**
     * The name of the layout to wrap around the view.
     */
    protected $layout = '';

    /**
     * An arbitrary list of asides/partials to be loaded into
     * the layout. The key is the declared name, the value the file
     */
    protected $asides = array();

    /**
     * A list of models to be autoloaded
     */
    protected $models = array();

    /**
     * A formatting string for the model autoloading feature.
     * The percent symbol (%) will be replaced with the model name.
     */
    protected $model_string = '%_model';

    /**
     * A list of helpers to be autoloaded
     */
    protected $helpers = array();

	protected $before_filters = array();
	protected $after_filters = array();

    /* --------------------------------------------------------------
     * GENERIC METHODS
     * ------------------------------------------------------------ */

    /**
     * Initialise the controller, tie into the CodeIgniter superobject
     * and try to autoload the models and helpers
     */
    public function __construct()
    {
        parent::__construct();

		$this->load->helper('inflector');

		$model = strtolower(singular(get_class($this)));
		if (file_exists(APPPATH . 'models/' . $this->_model_name($model) .'.php'))
		{
			$this->models[] = $model;
		}

        $this->_load_models();
        $this->_load_helpers();
    }

    /* --------------------------------------------------------------
     * VIEW RENDERING
     * ------------------------------------------------------------ */

    /**
     * Override CodeIgniter's despatch mechanism and route the request
     * through to the appropriate action. Support custom 404 methods and
     * autoload the view into the layout.
     */
    public function _remap($method, $parameters)
    {
        if (method_exists($this, $method))
        {
			$this->_run_filters('before', $method, $parameters);
			call_user_func_array(array($this, $method), $parameters);
			$this->_run_filters('after', $method, $parameters);
        }
        else
        {
            if (method_exists($this, '_404'))
            {
                call_user_func_array(array($this, '_404'), array($method));
            }
            else
            {
                show_404(strtolower(get_class($this)).'/'.$method);
            }
        }

        $this->_load_view();
    }

    /**
     * Automatically load the view, allowing the developer to override if
     * he or she wishes, otherwise being conventional.
     */
    protected function _load_view()
    {
        // If $this->view == FALSE, we don't want to load anything
		if ($this->view === FALSE)
		{
			return;
		}
		
		// If $this->view isn't empty, load it. If it isn't, try and guess based on the controller and action name
		$view = ( ! empty($this->view)) ? $this->view : $this->router->directory . $this->router->class . '/' . $this->router->method;

		// Load the view into $yield
		$data['yield'] = $this->load->view($view, $this->data, TRUE);

		// Do we have any asides? Load them.
		if (!empty($this->asides))
		{
			foreach ($this->asides as $name => $file)
			{
				$data['yield_'.$name] = $this->load->view($file, $this->data, TRUE);
			}
		}

		// Load in our existing data with the asides and view
		$data = array_merge($this->data, $data);

		// If we didn't specify the layout, try to guess it
		if ($this->layout !== FALSE)
		{
			if (is_string($this->layout) && ! empty($this->layout))
			{
				$layout = $this->layout;
			}
			elseif (file_exists(APPPATH . 'views/layouts/' . $this->router->class . '.php'))
			{
				$layout = $this->router->class;
			}
			else
			{
				$layout = 'application';
			}

			$this->load->view('layouts/' . $layout, $data);
		}
		else
		{
			$this->output->set_output($data['yield']);
		}
    }

    /* --------------------------------------------------------------
     * MODEL LOADING
     * ------------------------------------------------------------ */

    /**
     * Load models based on the $this->models array
     */
    private function _load_models()
    {
        foreach ($this->models as $model)
        {
            $this->load->model($this->_model_name($model), $model);
        }
    }

    /**
     * Returns the loadable model name based on
     * the model formatting string
     */
    protected function _model_name($model)
    {
        return str_replace('%', $model, $this->model_string);
    }

    /* --------------------------------------------------------------
     * HELPER LOADING
     * ------------------------------------------------------------ */

    /**
     * Load helpers based on the $this->helpers array
     */
    private function _load_helpers()
    {
        foreach ($this->helpers as $helper)
        {
            $this->load->helper($helper);
        }
    }

	protected function _run_filters($what, $action, $parameters)
	{
		$what = $what . '_filters';

		foreach ($this->$what as $filter => $details)
		{
			if (is_string($details))
			{
				$this->$details($action, $parameters);
			}
			elseif (is_array($details))
			{
				if (in_array($action, @$details['only'])
				|| ! in_array($action, @$details['except']))
				{
					$this->$filter($action, $parameters);
				}
			}
		}
	}
}
