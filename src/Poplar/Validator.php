<?php


namespace Poplar;


use Poplar\Database\DB;
use Poplar\Support\Str;

class Validator {
    public $request_validation_array;
    public $validation_error_log=[];
    private $nullable_values=[];
    private $un_nullable_functions=[
        'nullable',
        'required',
        'required_with',
        'required_with_all',
        'required_without',
        'required_without_all',
        'required_unless',
        'required_if',
    ];
    private $data_array;


    public function __construct($data_array) {
        // bring in the input values
        $this->data_array=$data_array;
    }

    /**
     * create a new instance of validation
     *
     * @param array $data
     * @param array $validation_array
     *
     * @return bool
     */
    public static function make(array $data, array $validation_array) {
        $self=new self($data);

        return $self->validate($validation_array);
    }

    /**
     * @param array $validation_array
     *
     * ==== USAGES ====
     *
     * @uses Validator::array()
     * @uses Validator::size()
     * @uses Validator::date()
     * @uses Validator::afterDate()
     * @uses Validator::beforeDate()
     * @uses Validator::different()
     * @uses Validator::digits()
     * @uses Validator::regex()
     * @uses Validator::between()
     * @uses Validator::min()
     * @uses Validator::max()
     * @uses Validator::characterLength()
     * @uses Validator::string()
     * @uses Validator::nullable()
     * @uses Validator::exists()
     * @uses Validator::unique()
     * @uses Validator::accepted()
     * @uses Validator::activeUrl()
     * @uses Validator::alphaDash()
     * @uses Validator::alphabetic()
     * @uses Validator::alphanumeric()
     * @uses Validator::numeric()
     * @uses Validator::boolean()
     * @uses Validator::confirmed()
     * @uses Validator::digitsBetween()
     * @uses Validator::email()
     * @uses Validator::same()
     * @uses Validator::url()
     * @uses Validator::required()
     * @uses Validator::requiredWith()
     * @uses Validator::requiredWithAll()
     * @uses Validator::requiredWithout()
     * @uses Validator::requiredWithoutAll()
     * @uses Validator::requiredUnless()
     * @uses Validator::requiredIf()
     * ===============
     *
     * @return bool
     */
    public function validate(array $validation_array) {
        // first explode off the '|' pipes which are the first part
        foreach ($validation_array as $key=>$value) {
            $all_functions=explode('|', $value);

            foreach ($all_functions as $function) {
                $function_args=[$key];
                // check if we need to explode off the functions arguments
                if (strpos($function, ':')!==FALSE) {
                    // there should only be one colon as the rest of the limiters should be commas
                    $function_args_raw=explode(':', $function)[1];
                    $function_args_raw=explode(',', $function_args_raw);
                    $function_args    =array_merge($function_args, $function_args_raw);
                    $function         =explode(':', $function)[0];
                }
                // allow to skip validation if nullable found. this should only continue if the value is empty and nullable.
                // for this to work properly always set nullable first in the validation options.
                if (
                    in_array($key, $this->nullable_values)&&
                    empty($this->data_array->$key)&&
                    ! in_array($function, $this->un_nullable_functions)
                ) {
                    continue;
                }
                // we now call that function and allow it to determine if an error is found
                call_user_func_array([$this, Str::camel($function)], $function_args);
            }
        }
        // TODO
        // we bind validation_errors here as we want the array regardless of if it has anything
        Application::bind('validation_errors', $this->validation_error_log);
        if ( ! empty($this->validation_error_log)) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param             $value
     * @param             $database_table
     * @param             $column_name
     * @param bool|string $ignore_id
     *
     * @return bool
     * @throws \Exception
     */
    private function unique($value, $database_table, $column_name, $ignore_id=FALSE) {
        $where_clause=[[$column_name, '=', $this->data_array->$value]];
        // set the ignore id, this will allow you to ignore an ID in the table
        if ($ignore_id!==FALSE) {
            array_push($where_clause, ['id', '!=', $ignore_id]);
        }
        // check if exists in the database
        try {
            $output = DB::table($database_table)->where($where_clause)->get();
        } catch (\Exception $e) {
            throw new \Exception("Invalid Unique Query for {$value}");
        }
        if ($output->count()>0) {
            if ($value==='email') {
                // give a special value for this
                $this->pushError($value, "$value provided is already registered on our system, please log in instead.");

                return FALSE;
            } else {
                // it is not unique
                $this->pushError($value, "$value is not a unique value");

                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * @param $value_name
     * @param $error_message
     *
     * @return bool
     */
    private function pushError($value_name, $error_message) {
        if ( ! isset($this->validation_error_log[ $value_name ])) {
            $this->validation_error_log[ $value_name ]=[];
        }
        array_push($this->validation_error_log[ $value_name ], $error_message);

        return TRUE;
    }

    /**
     * @param $value
     * @param $database_table
     * @param $column_name
     *
     * @return bool
     * @throws \Exception
     */
    private function exists($value, $database_table, $column_name) {
        $where_clause=[[$column_name, '=', $this->data_array->$value]];
        // check if exists in the database
        try {
            $output = DB::table($database_table)->where($where_clause)->get();
        } catch (\Exception $e) {
            throw new \Exception("Invalid Exists Query for {$value}");
        }
        if ($output->count()===0) {
            // it is not unique
            $this->pushError($value, "$value does not exist");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * Used in tandem with other methods to allow null
     *
     * @param $value
     */
    private function nullable($value) {
        // push the value to the nullable array for other functions to check
        array_push($this->nullable_values, $value);
    }

    private function accepted($value) {
        if ( ! (bool) $this->data_array->$value) {
            $this->pushError($value, "$value must be accepted");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param $value
     *
     * @return bool
     */
    private function activeUrl($value) {
        if ( ! checkdnsrr($this->data_array->$value)) {
            $this->pushError($value, "$value is not an active URL");

            return FALSE;
        }

        return TRUE;
    }

    private function array($value) {
        if ( ! is_array($this->data_array->$value)) {
            $this->pushError($value, "$value is not an array.");

            return FALSE;
        }

        return TRUE;
    }

    private function alphaDash($value) {
        // removed any dashes then check for alphanumeric
        // we can remove them as we do not care if they exist in this function
        $parsed_value =str_replace('-', '', $this->data_array->$value);
        $parsed_value =str_replace('_', '', $parsed_value);
        $parsed_value =str_replace(' ', '', $parsed_value);

        // call the original alphanumeric function as it does the same thing
        if ( ! ctype_alnum($parsed_value)) {
            $this->pushError($value, "$value contains non alphanumeric characters.");
        }
        return true;
    }

    private function alphabetic($value) {
        if ( ! ctype_alpha($this->data_array->$value)) {
            $this->pushError($value, "$value contains non alphabetic characters.");

            return FALSE;
        }

        return TRUE;
    }

    private function alphanumeric($value) {
        if ( ! ctype_alnum($this->data_array->$value)) {
            $this->pushError($value, "$value contains non alphanumeric characters.");
        }
        return true;
    }

    /**
     * @param $value
     *
     * @return bool
     */
    private function numeric($value) {
        if ( ! ctype_digit($this->data_array->$value)) {
            $this->pushError($value, "$value is not a numeric value.");

            return FALSE;
        }

        return TRUE;
    }

    private function afterDate($value, $date) {
        // first check its a date without repeating code
        if ( ! $this->date($value)) {
            return FALSE;
        }

        $formatted_value_date=strtotime($value);
        $formatted_after_date=strtotime($date);

        // make sure the date given is after the target date
        // this if is the opposite to log error
        if ($formatted_value_date<$formatted_after_date) {
            $readable_date=date('d/m/Y', $formatted_after_date);
            $this->pushError($value, "$value date must be after {$readable_date}");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * Check if this is a valid date
     *
     * @param $value
     *
     * @return bool
     */
    private function date($value) {
        if ( ! strtotime($this->data_array->$value)) {
            $this->pushError($value, "$value is not a valid date value");

            return FALSE;
        }

        return TRUE;
    }

    private function beforeDate($value, $date) {
        // first check its a date without repeating code
        if ( ! $this->date($value)) {
            return FALSE;
        }

        $formatted_value_date=strtotime($value);
        $formatted_after_date=strtotime($date);

        // make sure the date given is before the target date
        // this if is the opposite to log error
        if ($formatted_value_date>$formatted_after_date) {
            $readable_date=date('d/m/Y', $formatted_after_date);
            $this->pushError($value, "$value must be before {$readable_date}");

            return FALSE;
        }

        return TRUE;
    }

    private function between($value, $min, $max) {
        $str_length=strlen($this->data_array->$value);
        if ($str_length<$min||$str_length>$max) {
            $this->pushError($value, "$value is not between the character lengths (min: $min, max: $max)");

            return FALSE;
        }

        return TRUE;
    }

    private function boolean($value) {
        if ( ! is_bool($this->data_array->$value)) {
            $this->pushError($value, "$value is not a boolean value");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * Takes the name of this and finds if the value matches a 'confirmed_' version
     *
     * @param $value
     *
     * @return bool
     */
    private function confirmed($value) {
        $confirmed_value_name="confirm_$value";
        if ( ! isset($this->data_array->$confirmed_value_name)) {
            $this->pushError($value, "$value confirmation is not submitted");

            return FALSE;
        }
        if ($this->data_array->$confirmed_value_name!==$this->data_array->$value) {
            $this->pushError($value, "$value confirmation does not match the original value");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param $value
     * @param $target_value
     *
     * @return bool
     */
    private function different($value, $target_value) {
        if ($this->data_array->$value==$this->data_array->$target_value) {
            $this->pushError($value, "$value cannot be the same as {$target_value}");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param $value
     * @param $length
     *
     * @return bool
     */
    private function digits($value, $length) {
        if (strlen($this->data_array->$value)!==$length) {
            $this->pushError($value, "$value the digit length is not {$length}");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param $value
     * @param $min
     * @param $max
     *
     * @return bool
     */
    private function digitsBetween($value, $min, $max) {
        if (strlen($this->data_array->$value)<$min||strlen($this->data_array->$value)>$max) {
            $this->pushError($value, "$value is not between the digit lengths (min: $min, max: $max)");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param $value
     *
     * @return bool
     */
    private function email($value) {
        if ( ! filter_var($this->data_array->$value, FILTER_VALIDATE_EMAIL)) {
            $this->pushError($value, "The email provided is not a valid email address");
        }

        return TRUE;
    }

    /**
     * @param $value
     * @param $length
     *
     * @return bool
     */
    private function max($value, $length) {
        if (strlen($this->data_array->$value)>$length) {
            $this->pushError($value, "$value cannot be larger than $length characters");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param $value
     * @param $length
     *
     * @return bool
     */
    private function min($value, $length) {
        if (strlen($this->data_array->$value)<$length) {
            $this->pushError($value, "$value cannot be smaller than $length characters");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param $value
     * @param $regex
     */
    private function regex($value, $regex) {
    }

    /**
     * @param       $value
     * @param       $field
     * @param array ...$field_values
     *
     * @return bool
     */
    private function requiredIf($value, $field, ...$field_values) {
        $filtered=array_filter($field_values, function ($field_value) use ($field) {
            return $this->data_array->$field==$field_value;
        });
        if (count($filtered)>0) {
            return $this->required($value);
        }

        return TRUE;
    }

    /**
     * @param $value
     *
     * @return bool
     */
    private function required($value) {
        if (empty($this->data_array->$value)) {
            $this->pushError($value, "$value is required");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param       $value
     * @param       $field
     * @param array ...$field_values
     *
     * @return bool
     */
    private function requiredUnless($value, $field, ...$field_values) {
        $filtered=array_filter($field_values, function ($field_value) use ($field) {
            return $this->data_array->$field==$field_value;
        });
        if (count($filtered)===0) {
            return $this->required($value);
        }

        return TRUE;
    }

    private function requiredWith($value, ...$fields) {
        // loop through all fields and check if they are empty
        // if any of the specified fields are empty then we need to check required on the value
        $filtered=array_filter($fields, function ($field) {
            return empty($this->data_array->$field);
        });
        if (count($filtered)===0) {
            return $this->required($value);
        }

        // if it reaches here then we do not need a check
        return TRUE;
    }


    /**
     * @param       $value
     * @param array ...$fields
     *
     * @return bool
     */
    private function requiredWithAll($value, ...$fields) {
        // if at least one is empty then it should not require the value
        $filtered=array_filter($fields, function ($field) {
            return empty($this->data_array->$field);
        });
        // check how many are filtered, match them with the other fields
        if (count($filtered)===count($fields)) {
            return $this->required($value);
        }

        return TRUE;
    }

    /**
     * @param       $value
     * @param array ...$fields
     *
     * @return bool
     */
    private function requiredWithout($value, ...$fields) {
        $filtered=array_filter($fields, function ($field) {
            return empty($this->data_array->$field);
        });

        if (count($filtered)>0) {
            return $this->required($value);
        }

        return TRUE;
    }

    /**
     * @param       $value
     * @param array ...$fields
     *
     * @return bool
     */
    private function requiredWithoutAll($value, ...$fields) {
        $filtered=array_filter($fields, function ($field) {
            return ! empty($this->data_array->$field);
        });
        // check how many are filtered, match them with the other fields
        if (count($filtered)===count($fields)) {
            return $this->required($value);
        }

        return TRUE;
    }

    /**
     * Match a field value to a target value even if its null
     * Warning, This function uses ?? operator which is php version 7+
     *
     * @param $value
     * @param $field
     *
     * @return bool
     */
    private function same($value, $field) {
        if ($this->data_array->$value??NULL!==$this->data_array->$field??NULL) {
            $this->pushError($value, "{$value} has to be the same as {$field}");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param $value
     * @param $size
     *
     * @return bool
     */
    private function size($value, $size) {
        // check the string size
        // OR
        // check if the numeric value matches
        // OR
        // check the array count
        if (
            (is_string($this->data_array->$value)&&
             strlen($this->data_array->$value)!==(int) $size)
            ||
            (is_numeric($this->data_array->$value)&&
             (int) $this->data_array->$value!==(int) $size)
            ||
            (is_array($this->data_array->$value)&&
             count($this->data_array->$value)!==(int) $size)
        ) {
            $this->pushError($value, "{$value} has to be the same length as {$size}");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param $value
     *
     * @return bool
     */
    private function string($value) {
        if ( ! is_string($value)) {
            $this->pushError($value, "{$value} must be a string");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * Validate a url as filter_var
     *
     * @param $value
     *
     * @return bool
     */
    private function url($value) {
        if ( ! filter_var($value, FILTER_VALIDATE_URL)) {
            $this->pushError($value, "{$value} must be a valid URL");

            return FALSE;
        }

        return TRUE;
    }

    /**
     * @param $value
     * @param $max
     *
     * @return bool
     */
    private function characterLength($value, $max) {
        if (strlen($this->data_array->$value)>$max) {
            $this->pushError($value, "$value exceeds maximum character length ($max)");

            return FALSE;
        }

        return TRUE;
    }

}
