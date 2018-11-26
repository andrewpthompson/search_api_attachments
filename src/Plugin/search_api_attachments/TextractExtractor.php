<?php

namespace Drupal\search_api_attachments\Plugin\search_api_attachments;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api_attachments\TextExtractorPluginBase;
use Drupal\file\Entity\File;

/**
 * Provides python pdf2text extractor.
 *
 * @SearchApiAttachmentsTextExtractor(
 *   id = "textract_extractor",
 *   label = @Translation("Python Textract Extractor"),
 *   description = @Translation("Adds python Textract extractor support."),
 * )
 */
class TextractExtractor extends TextExtractorPluginBase {

  /**
   * Extract text from a file using Python Textract library.
   *
   * @param \Drupal\file\Entity\File $file
   *   A file object.
   *
   * @return string
   *   The text extracted from the file.
   */
  public function extract(File $file) {
      $filepath = $this->getRealpath($file->getFileUri());
      // Restore the locale.
      $python_path = $this->configuration['python_path'];
      $python_textract_script = realpath($this->configuration['python_textract_script']);
      $cmd = escapeshellcmd($python_path) . ' ' . escapeshellarg($python_textract_script) . ' -e utf_8 ' . escapeshellarg($filepath);
      // UTF-8 multibyte characters will be stripped by escapeshellargs() for
      // the default C-locale.
      // So temporarily set the locale to UTF-8 so that the filepath remains
      // valid.
      $backup_locale = setlocale(LC_CTYPE, '0');
      setlocale(LC_CTYPE, $backup_locale);
      // Support UTF-8 commands.
      // @see http://www.php.net/manual/en/function.shell-exec.php#85095
      shell_exec("LANG=en_US.utf-8");
      return shell_exec($cmd);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['python_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to python executable'),
      '#description' => $this->t('Enter the path to python executable. Example: "python".'),
      '#default_value' => $this->configuration['python_path'],
      '#required' => TRUE,
    ];
    $form['python_textract_script'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full path to the python textract script'),
      '#description' => $this->t('Enter the full path to the python textract script. Example: "/usr/bin/textract".'),
      '#default_value' => $this->configuration['python_textract_script'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Check that the file exists.
    $python_path = $values['text_extractor_config']['python_path'];
    $python_textract_script = $values['text_extractor_config']['python_textract_script'];
    if (!file_exists($python_textract_script)) {
      if (isset($form['text_extractor_config']['python_textract_script'])) {
        $form_state->setError($form['text_extractor_config']['python_textract_script'], $this->t('The file %path does not exist.', ['%path' => $python_textract_script]));
      }
    }
    // Check that the file is an executable Python Script.
    else {
      $cmd = escapeshellcmd($python_path) . ' ' . escapeshellarg($python_textract_script);
      $cmd = escapeshellarg($python_textract_script);
      exec($cmd, $output, $return_code);
      // $return_code = 2 if run like this with no file supplied
      if ($return_code != 2) {
        if (isset($form['text_extractor_config']['python_path']) && isset($form['text_extractor_config']['python_textract_script'])) {
          $form_state->setError($form['text_extractor_config']['python_path'], '');
          $form_state->setError($form['text_extractor_config']['python_textract_script'], $this->t('Python textract script file is not executable. Return code='.$return_code));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['python_path'] = $form_state->getValue(array('text_extractor_config', 'python_path'));
    $this->configuration['python_textract_script'] = $form_state->getValue(array('text_extractor_config', 'python_textract_script'));
    parent::submitConfigurationForm($form, $form_state);
  }

}
