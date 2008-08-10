<?php
/**
 * Represents a monetary value - USD are supported by default and others can be added via {@link defineCurrency()}
 * 
 * @copyright  Copyright (c) 2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fMoney
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2008-08-10]
 */
class fMoney
{
	/**
	 * The number of decimal places to use for values
	 * 
	 * @var integer
	 */
	static private $currencies = array(
		'USD' => array(
			'name'      => 'United States Dollar',
			'symbol'    => '$',
			'precision' => 2,
			'value'     => '1.00000000'
		)
	);
	
	/**
	 * The ISO code (three letters, e.g. 'USD') for the default currency
	 * 
	 * @var string
	 */
	static private $default_currency = NULL;
	
	/**
	 * A callback to process all mmoney values through
	 * 
	 * @var callback
	 */
	static private $format_callback = NULL;
	
	
	/**
	 * Allows adding a new currency, or modifying an existing one
	 * 
	 * @param string  $iso_code   The ISO code (three letters, e.g. 'USD') for the currency
	 * @param string  $name       The name of the currency
	 * @param string  $symbol     The symbol for the currency
	 * @param integer $precision  The number of digits after the decimal separator to store
	 * @param string  $value      The value of the currency relative to some common standard between all currencies
	 * @return void
	 */
	static public function defineCurrency($iso_code, $name, $symbol, $precision, $value)
	{
		self::$currencies[$iso_code] = array(
			'name'      => $name,
			'symbol'    => $symbol,
			'precision' => $precision,
			'value'     => $value
		);	
	}
	
	
	/**
	 * Allows retrieving information about a currency
	 * 
	 * @internal
	 * 
	 * @param string  $iso_code  The ISO code (three letters, e.g. 'USD') for the currency
	 * @param string  $element   The element to retrieve: 'name', 'symbol', 'precision', 'value'
	 * @return void
	 */
	static public function getCurrencyInfo($iso_code, $element=NULL)
	{
		if (!isset(self::$currencies[$iso_code])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The currency specified, %s$1, is not a valid currency. Must be one of: %s$2.',
					fCore::dump($iso_code),
					join(', ', array_keys(self::$currencies))
				)
			);		
		}
		
		if (!$element === NULL) {
			return self::$currencies[$iso_code];		
		}
		
		if (!isset(self::$currencies[$iso_code][$element])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The element specified, %s$1, is not valid. Must be one of: %s$2.',
					fCore::dump($element),
					join(', ', array_keys(self::$currencies[$iso_code]))
				)
			);	
		}
		
		return self::$currencies[$iso_code][$element];
	}
	
	
	/**
	 * Allows setting a callback to translate or modify any return values from {@link format()}
	 * 
	 * @param  callback $callback  The callback to pass all fNumber objects to. Should accept an fNumber object and a string currency abbreviation and return a single string.
	 * @return void
	 */
	static public function registerFormatCallback($callback)
	{
		self::$format_callback = $callback;
	}
	
	
	/**
	 * Sets the default currency to use when creating fMoney objects
	 * 
	 * @param string  $iso_code  The ISO code (three letters, e.g. 'USD') for the new default currency
	 * @return void
	 */
	static public function setDefaultCurrency($iso_code)
	{
		if (!isset(self::$currencies[$iso_code])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The currency specified, %s$1, is not a valid currency. Must be one of: %s$2.',
					fCore::dump($iso_code),
					join(', ', array_keys(self::$currencies))
				)
			);		
		}
		
		self::$default_currency = $iso_code;
	}
	
	
	/**
	 * The raw monetary value
	 * 
	 * @var fNumber
	 */
	private $amount;
	
	/**
	 * The ISO code or the currency of this value
	 * 
	 * @var string
	 */
	private $currency;
	
	
	/**
	 * Creates the monetary to represent, with an optional currency
	 * 
	 * @throws fValidationException
	 * 
	 * @param  fNumber|string $amount    The monetary value to represent, should never be a float since those are imprecise
	 * @param  string         $currency  The currency ISO code (three letters, e.g. 'USD') for this value
	 * @return fMoney
	 */
	public function __construct($amount, $currency=NULL)
	{
		if ($currency !== NULL && !isset(self::$currencies[$currency])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The currency specified, %s$1, is not a valid currency. Must be one of: %s$2.',
					fCore::dump($abbreviation),
					join(', ', array_keys(self::$currencies))
				)
			);		
		}
		
		if ($currency === NULL && self::$default_currency === NULL) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'No currency was specified and no default currency has been set'
				)
			);	
		}
		
		$this->currency = ($currency !== NULL) ? $currency : self::$default_currency;
		
		// We use an extra digit of precision with the fNumber object so we can round properly
		$precision    = self::getCurrencyInfo($this->currency, 'precision') + 1;
		$this->amount = new fNumber($amount, $precision);
	}
	
	
	/**
	 * Returns the monetary value without a currency symbol or thousand separator (e.g. 2000.12)
	 * 
	 * @return string  The monetary value without currency symbol or thousands separator
	 */
	public function __toString()
	{
		return $this->round()->__toString();
	}
	
	
	/**
	 * Adds the passed monetary value to the current one
	 * 
	 * @param  fMoney $addend  The money object to add
	 * @return fMoney  The sum of the monetary values in this currency
	 */
	public function add(fMoney $addend)
	{
		$converted_addend = $addend->convert($this->currency)->amount;
		$new_amount       = $this->amount->add($converted_addend);
		return new fMoney($new_amount, $this->currency);
	}
	
	
	/**
	 * Splits the current value into multiple parts ensuring that the sum of the results is exactly equal to this amount
	 * 
	 * @throws fValidationException
	 * 
	 * @param  fNumber|string $ratio1      The ratio of the first amount to this amount
	 * @param  fNumber|string $ratio2,...  The ratio of the second amount to this amount
	 * @return array  fMoney objects each with the appropriate ratio of the current amount
	 */
	public function allocate($ratio1, $ratio2)
	{
		$ratios = func_get_args();
		
		$total = new fNumber('0', 10);
		foreach ($ratios as $ratio) {
			$total = $total->add($ratio);	
		}
		
		if (!$total->eq('1.0')) {
			$ratio_values = array();
			foreach ($ratios as $ratio) {
				$ratio_values[] = ($ratio instanceof fNumber) ? $ratio->__toString() : (string) $ratio;	
			}
			
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The ratios specified (%s) combined are not equal to 1',
					join(', ', $ratio_values)
				)
			);		
		}
		
		$truncate_precision = self::getCurrencyInfo($this->currency, 'precision');
		
		if ($truncate_precision == 0) {
			$smallest_amount = new fNumber('1');
		} else {
			$smallest_amount = new fNumber('0.' . str_pad('', $truncate_precision-1, '0') . '1');	
		}
		$smallest_money = new fMoney($smallest_amount, $this->currency);
		
		$monies = array();
		$sum    = new fNumber('0', $truncate_precision);
		
		foreach ($ratios as $ratio) {
			$new_amount = $this->amount->mul($ratio)->trunc($truncate_precision);
			$sum = $sum->add($new_amount);
			$monies[] = new fMoney($new_amount, $this->currency);	
		}
		
		$rounded_amount = $this->round();
		
		while ($sum->lt($rounded_amount)) {
			foreach ($monies as &$money) {
				if ($sum->eq($rounded_amount)) {
					break 2;
				}
				$money = $money->add($smallest_money);
				$sum   = $sum->add($smallest_amount);
			}	
		}
		
		return $monies;
	}
	
	
	/**
	 * Converts this money amount to another currency
	 * 
	 * @param  string $new_currency  The ISO code (three letters, e.g. 'USD') for the new currency  
	 * @return fMoney  A new fMoney object representing this amount in the new currency
	 */
	public function convert($new_currency)
	{
		if ($new_currency == $this->currency) {
			return $this;	
		}
		
		if (!isset(self::$currencies[$new_currency])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The currency specified, %s$1, is not a valid currency. Must be one of: %s$2.',
					fCore::dump($new_currency),
					join(', ', array_keys(self::$currencies))
				)
			);		
		}		
		
		$currency_value     = self::getCurrencyInfo($this->currency, 'value');
		$new_currency_value = self::getCurrencyInfo($new_currency, 'value');
		$new_precision      = self::getCurrencyInfo($new_currency, 'precision') + 1;
		
		$new_amount = $this->amount->mul($currency_value, 8)->div($new_currency_value, $new_precision);
		 
		return new fMoney($new_amount, $new_currency);
	}
	
	
	/**
	 * Checks to see if two monetary values are equal
	 * 
	 * @param  fMoney $money  The money object to compare to
	 * @return boolean  If the monetary values are equal
	 */
	public function eq(fMoney $money)
	{
		return $this->round()->eq($money->convert($this->currency)->round());
	}
	
	
	/**
	 * Formats the amount
	 * 
	 * @throws fValidationException
	 * 
	 * @return string  The formatted (and possibly converted) value
	 */
	public function format()
	{
		if (self::$format_callback !== NULL) {
			return call_user_func(self::$format_callback, $this->value, $this->currency);
		}
		
		// We can't use number_format() since it takes a float and we have a
		// string that can not be losslessly converted to a float
		$number   = $this->__toString();
		$parts    = explode('.', $number);
		
		$integer  = $parts[0];
		$fraction = (!isset($parts[1])) ? '' : $parts[1];
		
		$sign     = '';
		if ($integer[0] == '-') {
			$sign    = '-';
			$integer = substr($integer, 1);	
		}
		
		$int_sections = array();
		for ($i = strlen($integer)-3; $i > 0; $i -= 3) {
			$int_sections[] = substr($integer, $i, 3);	
		}
		$int_sections[] = substr($integer, 0, $i+3);
		
		$symbol   = self::getCurrencyInfo($this->currency, 'symbol');
		$integer  = join(',', $int_sections);
		$fraction = (strlen($fraction)) ? '.' . $fraction : '';
		
		return $symbol . $sign . $integer . $fraction;
	}
	
	
	/**
	 * Returns the fNumber object representing the amount
	 * 
	 * @return fNumber  The amount of this monetary value
	 */
	public function getAmount()
	{
		return $this->amount;
	}
	
	
	/**
	 * Returns the currency ISO code
	 * 
	 * @return string  The currency ISO code (three letters, e.g. 'USD')
	 */
	public function getCurrency()
	{
		return $this->currency;
	}
	
	
	/**
	 * Checks to see if this value is greater than the one passed
	 * 
	 * @param  fMoney $money  The money object to compare to
	 * @return boolean  If this value is greater than the one passed
	 */
	public function gt(fMoney $money)
	{
		return $this->round()->gt($money->convert($this->currency)->round());
	}
	
	
	/**
	 * Checks to see if this value is greater than or equal to the one passed
	 * 
	 * @param  fMoney $money  The money object to compare to
	 * @return boolean  If this value is greater than or equal to the one passed
	 */
	public function gte(fMoney $money)
	{
		return $this->round()->gte($money->convert($this->currency)->round());
	}
	
	
	/**
	 * Checks to see if this value is less than the one passed
	 * 
	 * @param  fMoney $money  The money object to compare to
	 * @return boolean  If this value is less than the one passed
	 */
	public function lt(fMoney $money)
	{
		return $this->round()->lt($money->convert($this->currency)->round());
	}
	
	
	/**
	 * Checks to see if this value is less than or equal to the one passed
	 * 
	 * @param  fMoney $money  The money object to compare to
	 * @return boolean  If this value is less than or equal to the one passed
	 */
	public function lte(fMoney $money)
	{
		return $this->round()->lte($money->convert($this->currency)->round());
	}
	
	
	/**
	 * Mupltiplies this monetary value times the number passed
	 * 
	 * @throws fValidationException
	 * 
	 * @param  fNumber|string $multiplicand  The number of times to multiply this ammount - don't use a float since they are imprecise
	 * @return fMoney  The product of the monetary value and the multiplicand passed
	 */
	public function multiply($multiplicand)
	{
		$new_amount = $this->amount->mul($multiplicand);
		return new fMoney($new_amount, $this->currency);
	}
	
	
	/**
	 * Rounds the amount fNumber object to the exact precision for this currency
	 * 
	 * @return fNumber  The rounded amount
	 */
	protected function round()
	{
		$precision = self::getCurrencyInfo($this->currency, 'precision');
		return $this->amount->round($precision);	
	}
	
	
	/**
	 * Subtracts the passed monetary value from the current one
	 * 
	 * @param  fMoney $subtrahend  The money object to subtract
	 * @return fMoney  The difference of the monetary values in this currency
	 */
	public function subtract(fMoney $subtrahend)
	{
		$converted_subtrahend = $subtrahend->convert($this->currency)->amount;
		$new_amount           = $this->amount->sub($converted_subtrahend);
		return new fMoney($new_amount, $this->currency);
	}
}



/**
 * Copyright (c) 2008 William Bond <will@flourishlib.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */