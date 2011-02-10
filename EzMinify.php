<?php
/*
  Plugin Name: EzMinify
  Description: Minify and concat all Javascript and CSS automagically
  Version: 0.1
  Author: Ezra Pool <ezra@tsdme.nl>
  License: FreeBSD
*/

/**
 * Minify and concat all Javascript and CSS automagically
 * @name EzMinify
 * @version 0.1
 * @author Ezra Pool <ezra@tsdme.nl>
 * @license http://www.freebsd.org/copyright/freebsd-license.html FreeBSD
 */

/**
 * Copyright 2011 Ezra Pool. All rights reserved.

  Redistribution and use in source and binary forms, with or without modification, are
  permitted provided that the following conditions are met:

     1. Redistributions of source code must retain the above copyright notice, this list of
        conditions and the following disclaimer.

     2. Redistributions in binary form must reproduce the above copyright notice, this list
        of conditions and the following disclaimer in the documentation and/or other materials
        provided with the distribution.

  THIS SOFTWARE IS PROVIDED BY EZRA POOL ``AS IS'' AND ANY EXPRESS OR IMPLIED
  WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
  FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL EZRA POOL OR
  CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
  CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
  SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
  ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
  NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
  ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

  The views and conclusions contained in the software and documentation are those of the
  authors and should not be interpreted as representing official policies, either expressed
  or implied, of Ezra Pool.
 */
    class EzMinify{
        const PATHTOMINIFY = "/wp-content/plugins/EzMinify/min/f=";
        const CDN = 0x1;
        const LOCAL = 0x2;
        
        function  __construct() {
            add_action('wp_print_scripts', array(&$this, 'javascript'));
            add_action('wp_print_styles', array(&$this, 'css'));
        }
        public function javascript(){
            global $wp_scripts;
            $clonejs = clone $wp_scripts;

            ob_start();
            $clonejs->do_items();
            ob_end_clean();

            $i = 0;
            $minified = array();

            foreach($clonejs->done as $scriptname){
                $script = $clonejs->registered[$scriptname];

                if(strpos($script->src, 'http') === false){
                    $minified[$i]['scripts'][$scriptname] = $script->src;
                    $minified[$i]['ver'] += $script->ver;
                    $minified[$i]['type'] = self::LOCAL;
                    foreach($script->deps as $dep){
                      if(!is_array($minified[$i]['deps']) || !in_array($dep, $minified[$i]['deps'])){
                        $minified[$i]['deps'][] = $dep;
                      }
                    }
                }else{
                    $i++;
                    $minified[$i]['scripts'][$scriptname] = $script->src;
                    $minified[$i]['ver'] = $script->ver;
                    $minified[$i]['type'] = self::CDN;
                    foreach($script->deps as $dep){
                      if(!is_array($minified[$i]['deps']) || !in_array($dep, $minified[$i]['deps'])){
                        $minified[$i]['deps'][] = $dep;
                      }
                    }
                    $i++;
                }
                
                wp_deregister_script($scriptname);
            }

            //remove and replace dependencies by groups
            foreach($minified as $key => &$group){
              if(array_key_exists('deps', $group)){
                foreach($group['deps'] as $index => &$dep){
                  foreach($minified as $key2 => $group2){
                    if(array_key_exists($dep, $group2['scripts'])){
                      if($key == $key2){
                        array_splice($group['deps'], $index, 1);
                      }else{
                       $dep = "EzMinify-{$key2}";
                      }
                    }
                  }
                }
              }
            }
            unset($group);

            //loop over all groups to add them to the queue
            foreach($minified as $key => &$group){
              if(array_key_exists('deps', $group)){
                $group['deps'] = array_unique($group['deps']);
              }
              if($group['type'] == self::LOCAL){
                //concat and minify all scripts into one
                wp_enqueue_script("EzMinify-{$key}", self::PATHTOMINIFY.implode($group['scripts'], ','), $group['deps'], $group['ver'], false);
              }else{
                //output as normal
                wp_enqueue_script("EzMinify-{$key}", implode($group['scripts'], ','), $group['deps'], $group['ver'], false);
              }
            }
        }
        public function css(){
            global $wp_styles;
            $clonecss = clone $wp_styles;

            ob_start();
            $clonecss->do_items();
            ob_end_clean();

            $i = 0;
            $minified = array();

            foreach($clonecss->done as $stylename){
                $style = $clonecss->registered[$stylename];

                if(strpos($style->src, 'http') === true){
                    $i++;
                    $minified[$i]['styles'][$stylename] = $style->src;
                    $minified[$i]['ver'] = $style->ver;
                    $minified[$i]['media'] = $style->args;
                    $minified[$i]['type'] = self::CDN;
                    foreach($style->deps as $dep){
                      if(!is_array($minified[$i]['deps']) || !in_array($dep, $minified[$i]['deps'])){
                        $minified[$i]['deps'][] = $dep;
                      }
                    }
                    $i++;
                }elseif($minified[$i]['media'] != $style->args){
                    $i++;
                    $minified[$i]['styles'][$stylename] = $style->src;
                    $minified[$i]['ver'] = $style->ver;
                    $minified[$i]['media'] = $style->args;
                    $minified[$i]['type'] = self::LOCAL;
                    foreach($style->deps as $dep){
                      if(!is_array($minified[$i]['deps']) || !in_array($dep, $minified[$i]['deps'])){
                        $minified[$i]['deps'][] = $dep;
                      }
                    }
                }else{
                    $minified[$i]['styles'][$stylename] = $style->src;
                    $minified[$i]['ver'] += $style->ver;
                    $minified[$i]['media'] = $style->args;
                    $minified[$i]['type'] = self::LOCAL;
                    foreach($style->deps as $dep){
                      if(!is_array($minified[$i]['deps']) || !in_array($dep, $minified[$i]['deps'])){
                        $minified[$i]['deps'][] = $dep;
                      }
                    }
                }
                
                wp_deregister_style($stylename);
            }

            //remove and replace dependencies by groups
            foreach($minified as $key => &$group){
              if(array_key_exists('deps', $group)){
                foreach($group['deps'] as $index => &$dep){
                  foreach($minified as $key2 => $group2){
                    if(array_key_exists($dep, $group2['styles'])){
                      if($key == $key2){
                        array_splice($group['deps'], $index, 1);
                      }else{
                       $dep = "EzMinify-{$key2}";
                      }
                    }
                  }
                }
              }
            }
            unset($group);

            //loop over all groups to add them to the queue
            foreach($minified as $key => &$group){
              if(array_key_exists('deps', $group)){
                $group['deps'] = array_unique($group['deps']);
              }
              if($group['type'] == self::LOCAL){
                //concat and minify all styles into one
                wp_enqueue_style("EzMinify-{$key}", self::PATHTOMINIFY.implode($group['styles'], ','), $group['deps'], $group['ver'], $group['media']);
              }else{
                //output as normal
                wp_enqueue_style("EzMinify-{$key}", implode($group['styles'], ','), $group['deps'], $group['ver'], $group['media']);
              }
              
            }
        }
    }
    $ezminify = new EzMinify();
?>