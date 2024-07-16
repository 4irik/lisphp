(def ctor (lambda (a)
    (do
  (def inc (lambda (x) (do (set! a (+ a x)) a)))
  (def dec (lambda (x) (do (set! a (- a x)) a)))
  (def get (lambda ()      a))
  (lambda (msg)
    (cond (= 1 msg) inc
      (cond (= 2 msg) dec
        (cond (= 3 msg) get ())))))))