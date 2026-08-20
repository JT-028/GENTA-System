<?php
declare(strict_types=1);

namespace App\Controller\Component;

use Cake\Controller\Component;

/**
 * CAPTCHA Component
 * 
 * Provides simple math-based CAPTCHA for form protection
 * Can be upgraded to Google reCAPTCHA v3 for production
 */
class CaptchaComponent extends Component
{
    /**
     * Generate a math CAPTCHA challenge
     *
     * @return array ['question' => string, 'answer' => int]
     */
    public function generateChallenge(): array
    {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operation = rand(0, 1) ? '+' : '-';
        
        if ($operation === '-') {
            // Ensure positive result
            if ($num1 < $num2) {
                $temp = $num1;
                $num1 = $num2;
                $num2 = $temp;
            }
            $answer = $num1 - $num2;
        } else {
            $answer = $num1 + $num2;
        }
        
        $question = "$num1 $operation $num2 = ?";
        
        // Store in session
        $session = $this->getController()->getRequest()->getSession();
        $session->write('captcha_answer', $answer);
        $session->write('captcha_generated', time());
        
        return [
            'question' => $question,
            'answer' => $answer
        ];
    }
    
    /**
     * Verify CAPTCHA answer
     *
     * @param mixed $userAnswer User's answer
     * @return bool
     */
    public function verify($userAnswer): bool
    {
        $session = $this->getController()->getRequest()->getSession();
        $correctAnswer = $session->read('captcha_answer');
        $generatedTime = $session->read('captcha_generated');
        
        // Clear CAPTCHA from session after verification
        $session->delete('captcha_answer');
        $session->delete('captcha_generated');
        
        // CAPTCHA expires after 5 minutes
        if (!$generatedTime || (time() - $generatedTime) > 300) {
            return false;
        }
        
        if ($correctAnswer === null) {
            return false;
        }
        
        return (int)$userAnswer === (int)$correctAnswer;
    }
    
    /**
     * Check if CAPTCHA is required based on failed attempts
     *
     * @param int $failedAttempts Number of failed attempts
     * @return bool
     */
    public function isRequired(int $failedAttempts = 0): bool
    {
        // Require CAPTCHA after 2 failed attempts
        return $failedAttempts >= 2;
    }
}
